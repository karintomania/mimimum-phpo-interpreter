.PHONY: test

test:
	docker run --rm --volume '.:/app' php:8.5-cli php /app/test.php
