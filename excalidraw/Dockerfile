# Stage 1 — build Excalidraw from source with a custom WS server URL
FROM node:20-alpine AS builder

RUN apk add --no-cache git

WORKDIR /app

# Clone the official Excalidraw repo (shallow for speed)
RUN git clone --depth=1 https://github.com/excalidraw/excalidraw .

# Install dependencies
RUN yarn install --frozen-lockfile

# Patch out the iframe guard IN SOURCE before vite compiles it.
# Excalidraw renders "I'm not a pretzel!" when window.self !== window.top.
# We find the source file(s) containing that guard and replace the expression
# with the literal false so the guard is dead-code-eliminated at build time.
# BusyBox grep (Alpine) has no --include; use find+xargs instead.
RUN find /app -type f \( -name "*.ts" -o -name "*.tsx" -o -name "*.js" -o -name "*.jsx" \) \
      ! -path "*/node_modules/*" | \
    xargs grep -rl "pretzel" 2>/dev/null | \
    while read f; do \
      echo "Patching iframe guard in: $f"; \
      sed -i 's/window\.self !== window\.top/false/g' "$f"; \
      sed -i 's/window\.self!==window\.top/false/g' "$f"; \
    done

# Point collaboration to our own room relay.
# VITE_APP_WS_SERVER_URL must be reachable from the *browser*, so use the
# host-visible address (localhost:9090 exposed by the room container).
ARG VITE_APP_WS_SERVER_URL=ws://localhost:9090
ENV VITE_APP_WS_SERVER_URL=$VITE_APP_WS_SERVER_URL

ARG VITE_BASE_PATH=/
ENV VITE_BASE_PATH=$VITE_BASE_PATH

# Build the production bundle — run vite directly so --base is honoured
# (yarn build at repo root uses a workspace script that doesn't forward extra args)
RUN cd excalidraw-app && npx vite build --base=$VITE_BASE_PATH

# Stage 2 — serve the static bundle with nginx
FROM nginx:1.25-alpine

COPY --from=builder /app/excalidraw-app/build /usr/share/nginx/html

# SPA fallback so deep-linked routes work inside the iframe
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
