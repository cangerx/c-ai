FROM node:20-alpine AS builder

ARG FRONTEND_REPO=https://github.com/cangerx/cang-ai-web.git
ARG FRONTEND_REF=main
ARG NEXT_PUBLIC_API_URL
ARG NEXT_PUBLIC_APP_URL

ENV NEXT_TELEMETRY_DISABLED=1
ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL}
ENV NEXT_PUBLIC_APP_URL=${NEXT_PUBLIC_APP_URL}

WORKDIR /src

RUN apk add --no-cache git \
    && git init \
    && git remote add origin "${FRONTEND_REPO}" \
    && git fetch --depth 1 origin "${FRONTEND_REF}" \
    && git checkout --detach FETCH_HEAD \
    && git remote remove origin \
    && mkdir -p public \
    && if [ -f package-lock.json ]; then npm ci; else npm install; fi \
    && npm run build

FROM node:20-alpine AS runner

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

WORKDIR /app

COPY --from=builder /src/.next/standalone ./
COPY --from=builder /src/.next/static ./.next/static
COPY --from=builder /src/public ./public

EXPOSE 3000

CMD ["node", "server.js"]
