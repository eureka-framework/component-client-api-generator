# component-client-api-generator
[![Current version](https://img.shields.io/packagist/v/eureka/component-client-api-generator.svg?logo=composer)](https://packagist.org/packages/eureka/component-client-api-generator)
[![Supported PHP version](https://img.shields.io/static/v1?logo=php&label=PHP&message=>%3D8.3&color=777bb4)](https://packagist.org/packages/eureka/component-client-api-generator)
![CI](https://github.com/eureka-framework/component-client-api-generator/workflows/CI/badge.svg)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=eureka-framework_component-client-api-generator&metric=alert_status)](https://sonarcloud.io/dashboard?id=eureka-framework_component-client-api-generator)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=eureka-framework_component-client-api-generator&metric=coverage)](https://sonarcloud.io/dashboard?id=eureka-framework_component-client-api-generator)

## Why?

Libs to generate Client API / SDK based on OpenApi json schema.



## Installation

If you wish to install it in your project, require it via composer:

```bash
composer require eureka/component-client-api-generator
```



## Usage

Usage:
```php
<?php

// Sample code here
```


## Contributing

See the [CONTRIBUTING](CONTRIBUTING.md) file.


### Install / update project

You can install project with the following command:
```bash
make install
```

And update with the following command:
```bash
make update
```

NB: For the components, the `composer.lock` file is not committed.

### Testing & CI (Continuous Integration)

#### Tests
You can run unit tests (with coverage) on your side with following command:
```bash
make php/tests
```

You can run integration tests (without coverage) on your side with following command:
```bash
make php/integration
```

For prettier output (but without coverage), you can use the following command:
```bash
make php/testdox # run tests without coverage reports but with prettified output
```

#### Code Style
You also can run code style check with following commands:
```bash
make php/check
```

You also can run code style fixes with following commands:
```bash
make php/fix
```

#### Check for missing explicit dependencies
You can check if any explicit dependency is missing with the following command:
```bash
make php/deps
```

#### Static Analysis
To perform a static analyze of your code (with phpstan, lvl 9 at default), you can use the following command:
```bash
make php/analyse
```

To ensure you code still compatible with current supported version at Deezer and futures versions of php, you need to
run the following commands (both are required for full support):

Minimal supported version:
```bash
make php/min-compatibility
```

Maximal supported version:
```bash
make php/max-compatibility
```

#### CI Simulation
And the last "helper" commands, you can run before commit and push, is:
```bash
make ci  
```

## License

This project is currently under The MIT License (MIT). See [LICENSE](LICENSE) file for more information.
