<?php

declare(strict_types=1);

namespace App\UI\Console;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Redis\RedisSubscriber;
use Amp\Socket\InternetAddress;
use Amp\Websocket\Server\Rfc6455Acceptor;
use Amp\Websocket\Server\Websocket;
use Amp\Websocket\Server\WebsocketClientGateway;
use App\Infrastructure\WebSocket\BoardClientHandler;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Долгоживущий WebSocket-сервер на amphp.
 *
 * Отдельный контейнер: php-fpm живёт один запрос и держать соединения
 * не может. Связь с HTTP-частью — через Redis Pub/Sub.
 */
#[AsCommand(
    name: 'app:websocket:serve',
    description: 'Запускает WebSocket-сервер и транслирует в него события из Redis',
)]
final class WebSocketServeCommand extends Command
{
    public function __construct(
        private readonly string $redisUrl,
        private readonly string $realtimeChannel,
        private readonly LoggerInterface $realtimeLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Интерфейс для прослушивания', '0.0.0.0')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'TCP-порт', '8081')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'HTTP-путь для апгрейда', '/ws');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $path = (string) $input->getOption('path');

        $logger = $this->realtimeLogger;
        $errorHandler = new DefaultErrorHandler();

        $gateway = new WebsocketClientGateway();
        $clientHandler = new BoardClientHandler($gateway, $logger);

        $server = SocketHttpServer::createForDirectAccess($logger);
        $server->expose(new InternetAddress($host, $port));

        $websocket = new Websocket($server, $logger, new Rfc6455Acceptor(), $clientHandler);

        $router = new Router($server, $logger, $errorHandler);
        $router->addRoute('GET', $path, $websocket);

        $server->start($router, $errorHandler);

        $io->success(sprintf('WebSocket-сервер слушает ws://%s:%d%s', $host, $port, $path));
        $io->writeln(sprintf('Транслирую канал Redis «%s»', $this->realtimeChannel));

        // Подписка живёт в отдельной корутине: цикл событий продолжает
        // обслуживать HTTP/WS-соединения
        EventLoop::queue(function () use ($clientHandler, $logger): void {
            $this->consumeRedis($clientHandler, $logger);
        });

        // Ждём сигнал остановки контейнера, затем закрываем соединения
        $signal = \Amp\trapSignal([\SIGINT, \SIGTERM]);
        $io->writeln(sprintf('Получен сигнал %d, останавливаюсь', $signal));

        $server->stop();

        return Command::SUCCESS;
    }

    private function consumeRedis(BoardClientHandler $clientHandler, LoggerInterface $logger): void
    {
        while (true) {
            try {
                $subscriber = new RedisSubscriber(\Amp\Redis\createRedisConnector($this->redisUrl));
                $subscription = $subscriber->subscribe($this->realtimeChannel);

                $logger->info('Подписан на канал {channel}', ['channel' => $this->realtimeChannel]);

                foreach ($subscription as $message) {
                    $clientHandler->broadcast($message);
                }
            } catch (\Throwable $e) {
                // Обрыв связи с Redis не должен ронять сервер: клиенты
                // остаются подключёнными, подписка поднимается заново
                $logger->error('Подписка на Redis оборвалась: {error}', ['error' => $e->getMessage()]);
                \Amp\delay(1.0);
            }
        }
    }
}
