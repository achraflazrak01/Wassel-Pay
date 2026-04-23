  app.noor_bank:
    image: laravelphp/laravel:10.x
    container_name: app_noor_bank
    restart: unless-stopped
    environment:
      DB_CONNECTION: mysql
      DB_HOST: mysql.noor_bank
      DB_PORT: 3306
      DB_DATABASE: noor_bank_db
      DB_USERNAME: app_noor_bank
      DB_PASSWORD: app_noor_bank_pass
    ports:
      - "8001:8000"
    depends_on:
      - mysql.noor_bank
    networks:
      - wassel_network
      