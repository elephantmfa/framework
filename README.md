<p align="center"><a href="https://docs.elephantmfa.app"><img width="150px" height="150px" src="https://github.com/elephantmfa/docs/blob/master/assets/img/logo.svg"></a></p>
<p align="center">
<a href="https://packagist.org/packages/elephantmfa/framework"><img alt="Total Downloads" src="https://poser.pugx.org/elephantmfa/framework/downloads"></a>
<a href="https://packagist.org/packages/elephantmfa/framework"><img alt="Latest Stable Version" src="https://poser.pugx.org/elephantmfa/framework/version"></a>
<a href="https://packagist.org/packages/elephantmfa/framework"><img alt="License" src="https://poser.pugx.org/elephantmfa/framework/license"></a>
</p>

## About Elephant Mail Filtering Application (MFA)
---
> **Note:** This repository contains the core code of the ElephantMFA framework. If you want to build an application using ElephantMFA, visit the main [ElephantMFA repository](https://github.com/elephantmfa/elephantmfa).

ElephantMFA is a framework very tightly based on [Laravel](https://laravel.com), however it is not used for HTTP requests, but SMTP requests.
In it's current state, it is meant to be used as a Content filter for the likes of [Postfix](http://www.postfix.org/). It is designed to be a highly customizable mail filter.

The reason that this was made was because similar mail filters out there all are configured with configuration files and maybe minor hooks for adding custom functionality. While this works well for ease of configuring the system, it hinders one's ability to finaly tune for the exact configuration you need. This would then result in extensive source diving and modification.

Here comes ElephantMFA, a highly extensible *framework* written in PHP using ReactPHP, to write all of your own filtering code, to have it configured exactly as you need it.

## Learning ElephantMFA

The documentation for ElephantMFA is located on [Github](https://github.com/elephantmfa/docs) or at [docs.elephantmfa.app](https://docs.elephantmfa.app/).

## Contributing

Thank you for considering contributing to the ElephantMFA framework!

### Current contributors:
 * [Marisa Clardy](https://clardy.eu)

## License

The ElephantMFA framework is open-sourced software licensed under the [MIT license](LICENSE.md).
