
.PHONY: install
install: install_dependencies test docs

.PHONY: install_dependencies
install_dependencies:
	@echo [Installing dependencies]
	composer install

.PHONY: test
test:
	@echo [Executing tests]
	vendor/bin/phpunit

.PHONY: docs
docs:
	@echo [Creating docs]
	php vendor/bin/phpdoc

.PHONY: clean
clean:
	@echo [Cleaning workspace]
	rm -rf intermediate
	rm -rf dist/apidocs
	rm -rf vendor

.PHONY: inc_version
inc_version:
	@echo [Getting inc version]
	node scripts/inc_version

# Packagist looks for new tags in the git repo to find newly published
# packages
.PHONY: publish
publish: pre-publish inc_version
	@echo [Publishing package]
	git add .
	git commit -m "Increment version to $(shell cat VERSION)"
	git tag $(shell cat VERSION)
	git push -u origin master
	git push -u origin master --tags

# Verify that all tests pass, we are on master, and it is a clean branch
.PHONY: pre-publish
pre-publish: test
	@if [ $(shell git symbolic-ref --short -q HEAD) = "master" ]; then exit 0; else \
		echo "Current git branch does not appear to be 'master'. Refusing to publish."; exit 1; \
	fi
	@if [ $(shell git status --short) = ""]; then exit 0; else \
		echo "Current git branch appears to be dirty"; exit 1; \
	fi

# An internal LightStep target for regenerating the thrift protocol files
.PHONY: thrift
thrift:
	thrift -r -gen php -out thrift $(LIGHTSTEP_REPO_ROOT)/go/src/github.com/lightstep/common-go/crouton.thrift

# An internal LightStep target for regenerating the protobuf files
# In order to run this, it is assumed that you have the following projects cloned into the same
# directly as this project:
# https://github.com/lightstep/lightstep-tracer-common
# https://github.com/googleapis/googleapis
.PHONY: proto
proto:
	protoc --proto_path "$(PWD)/../googleapis:$(PWD)/../lightstep-tracer-common/" \
		--php_out="$(PWD)/lib/generated" \
		collector.proto google/api/annotations.proto google/api/http.proto
