FROM node:20-alpine

WORKDIR /app

RUN apk add --no-cache curl

COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund

COPY . .

EXPOSE 3000

CMD ["sh", "-c", "[ -d node_modules ] && [ -f node_modules/.package-lock.json ] || npm install --no-audit --no-fund; npm run dev"]
