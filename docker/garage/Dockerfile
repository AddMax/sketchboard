# Официальный образ Garage собран «from scratch»: в нём один статический
# бинарник и ни shell, ни wget. Нам нужны оба — для healthcheck и для
# bootstrap-скрипта, который заводит layout, бакеты и ключ при старте.
# Поэтому переносим бинарник в alpine.
FROM dxflrs/garage:v2.2.0 AS garage

FROM alpine:3.22

COPY --from=garage /garage /usr/local/bin/garage
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 3900 3902 3903

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["garage", "server"]
