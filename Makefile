
# Set up the default (i.e. - first) make entry.
start: web

bash:
	docker-compose run --rm idp bash

bashtests:
	docker-compose run --rm tests bash

behat:
	docker-compose run --rm tests bash -c "vendor/bin/behat --strict --stop-on-failure --append-snippets"

clean:
	docker-compose kill
	docker system prune -f

composer:
	docker-compose run --rm composer bash -c "composer install --no-scripts"

composerupdate:
	docker-compose run --rm composer bash -c "composer update --no-scripts"

enabledebug:
	docker-compose exec idp bash -c "/data/enable-debug.sh"

ps:
	docker-compose ps

test: composer web
	docker-compose run --rm tests whenavail idp 80 100 bash -c "vendor/bin/behat --strict --stop-on-failure --append-snippets"

web:
	docker-compose up -d idp sp pwmanager
