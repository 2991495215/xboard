FROM node:22-alpine

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev
COPY --chown=node:node server.mjs ./

USER node
CMD ["node", "server.mjs"]
