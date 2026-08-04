#generate-example-clients: generate-example-client-one generate-example-client-subsplit generate-example-client-miele
#
#generate-example-client-one:
#	$(DOCKER_RUN) php ./bin/openapi-client-generator ./example/openapi-client-one.yaml
#
#generate-example-client-subsplit:
#	$(DOCKER_RUN) php ./bin/openapi-client-generator ./example/openapi-client-subsplit.yaml
#
#generate-example-client-miele:
#	$(DOCKER_RUN) php ./bin/openapi-client-generator ./example/openapi-client-miele.yaml

generate-packages:
	$(DOCKER_RUN) php ./bin/openapi-generator ./example/config.php

migrations-local-unit-testing-coverage-guard: #### Ensure unit-testing enforces 100%% coverage ##*I*##
	($(DOCKER_RUN) php -r '$$makefile = "Makefile"; if (! file_exists($$makefile)) { exit; } $$contents = file_get_contents($$makefile); if (! is_string($$contents)) { exit; } if (str_contains($$contents, "# LOCAL: unit-testing-coverage-guard")) { exit; } $$coverageGuard = "vendor/bin/coverage-guard check ./var/tests-unit-clover-coverage.xml --config=./etc/qa/coverage-guard.php"; $$phpunit = "$$(DOCKER_RUN_WITH_SOCKET) vendor/bin/phpunit --colors=always -c ./etc/qa/phpunit.xml $$(shell $$(DOCKER_SHELL) php -r '"'"'if (function_exists(\"xdebug_get_code_coverage\")) { echo \" --coverage-text --coverage-html ./var/tests-unit-coverage-html --coverage-clover ./var/tests-unit-clover-coverage.xml\"; }'"'"')"; $$patch = "\n# LOCAL: unit-testing-coverage-guard\nunit-testing: ## Run tests ##*AE*##^unit-tests^##\n\t" . $$phpunit . "\n\t$$(DOCKER_SHELL) " . $$coverageGuard . "\nupdate-lock: ### Update lockfile ####\n\t$$(DOCKER_RUN_WITHOUT_NETWORK_FOR_COMPOSER) composer update --lock --no-scripts || $$(DOCKER_RUN) composer update --lock --no-scripts\n\t$$(MAKE) migrations-local-unit-testing-coverage-guard\n"; $$contents = preg_replace("/\n+## Catch-all/", $$patch . "\n## Catch-all", $$contents, 1); if (! is_string($$contents)) { exit; } file_put_contents($$makefile, $$contents);' || true)
