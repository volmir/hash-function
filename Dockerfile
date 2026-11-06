FROM php:8.3-cli

WORKDIR /app

COPY . .

CMD ["php", "run.php"]
