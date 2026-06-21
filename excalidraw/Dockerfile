# Stage 1 — build Excalidraw from source with a custom WS server URL
FROM node:20-alpine AS builder

RUN apk add --no-cache git

WORKDIR /app

# Clone the official Excalidraw repo (shallow for speed)
RUN git clone --depth=1 https://github.com/excalidraw/excalidraw .

# Install dependencies
RUN yarn install --frozen-lockfile

# Point collaboration to our own room relay.
# VITE_APP_WS_SERVER_URL must be reachable from the *browser*, so use the
# host-visible address (localhost:9090 exposed by the room container).
ARG VITE_APP_WS_SERVER_URL=ws://localhost:9090
ENV VITE_APP_WS_SERVER_URL=$VITE_APP_WS_SERVER_URL

# Build the production bundle
RUN yarn build

# Stage 2 — serve the static bundle with nginx
FROM nginx:1.25-alpine

COPY --from=builder /app/excalidraw-app/build /usr/share/nginx/html

# SPA fallback so deep-linked routes work inside the iframe
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
