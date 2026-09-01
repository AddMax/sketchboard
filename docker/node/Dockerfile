# syntax=docker/dockerfile:1
FROM node:24-alpine

ARG UID=1000
ARG GID=1000

# В образе node уже есть пользователь node (обычно 1000:1000);
# приводим его к UID/GID хоста, чтобы node_modules и правки не были под root
RUN apk add --no-cache bash \
    && if [ "$(id -u node)" != "${UID}" ] || [ "$(id -g node)" != "${GID}" ]; then \
         deluser node; \
         addgroup -g "${GID}" node 2>/dev/null || true; \
         adduser -u "${UID}" -G node -h /home/node -s /bin/bash -D node; \
       fi \
    && mkdir -p /app/node_modules /home/node/.npm \
    && chown -R "${UID}:${GID}" /app /home/node

COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app
USER node

EXPOSE 5173
ENTRYPOINT ["entrypoint"]
CMD ["npm", "run", "dev"]
