# Unique Order Identifier Field for Symphony CMS

-   Version: 2.0.0
-   Date: April 10 2020
-   [Release notes](https://github.com/pointybeard/unique_order_identifier_field/blob/master/CHANGELOG.md)
-   [GitHub repository](https://github.com/pointybeard/unique_order_identifier_field)

Field for [Symphony CMS](http://getsymphony.com) that auto-populates with a unique value that can be used for tasks like generating Order numbers in eCommerce systems. Once an order identifier has been set, it cannot be modified and will never be used again.

### Requirements

This extension requires PHP 7.3 or greater and depends on the following Composer libraries:

-   [Symphony CMS: Extended Base Class Library](https://github.com/pointybeard/symphony-extended)

## Installation

This is an extension for [Symphony CMS](http://getsymphony.com).

- Download or clone this repository and add it to the `/extensions` directory of your Symphony CMS installation.
- Run `composer update` from inside the `/extensions/unique_order_identifier_field` directory
- Finally, install it via the Symphony CMS  Systems > Extensions administration interface.

## Upgrading from version 1.x

Currently there is no upgrade path from 1.x to 2.x (although one may be provided in the future). It is suggested the 2.x release be treated as an entirely new field since it has seen not only a name change, but, also a major internal overhaul.

## Support

If you believe you have found a bug, please report it using the [GitHub issue tracker](https://github.com/pointybeard/unique_order_identifier_field/issues),
or better yet, fork the library and submit a pull request.

## Contributing

We encourage you to contribute to this project. Please check out the [Contributing documentation](https://github.com/pointybeard/unique_order_identifier_field/blob/master/CONTRIBUTING.md) for guidelines about how to get involved.

## License

"Unique Order Identifier Field for Symphony CMS" is released under the [MIT License](http://www.opensource.org/licenses/MIT).
