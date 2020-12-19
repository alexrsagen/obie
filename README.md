# Obie
Obie is a simple PHP framework. It aims to provide basic services needed for any web app.

## Usage
```
composer require alexrsagen/obie
```

Check out [alexrsagen/obie-sample-app](https://github.com/alexrsagen/obie-sample-app) to see an example app built with Obie.

### Migration guide from ZeroX
- Upgrade to PHP >= 8.0.0
- Replace all instances of "ZeroX" / "ZEROX" with "Obie" / "OBIE"
- Replace `Obie\Mime` with `Obie\Http\Mime`
- Replace `Obie\Router` with `Obie\Http\Router` <sup>[1]</sup>
- Replace `Obie\RouterInstance` with `Obie\Http\RouterInstance` <sup>[1]</sup>
- Replace `Obie\Route` with `Obie\Http\Route` <sup>[1]</sup>
- Replace `Obie\Http\Client` with `Obie\Http\Request` using its method `perform()`
- Replace `Obie\Util::formatBytes` with `Obie\Formatters\Bytes::toString`
- Replace `Obie\Util::formatTime` with `Obie\Formatters\Time::toRelativeString`
- Replace `Obie\ModelHelpers::getSingular` with `Obie\Formatters\EnglishNoun::toSingular`
- Replace `Obie\ModelHelpers::getPlural` with `Obie\Formatters\EnglishNoun::toPlural`
- Replace `Obie\ModelHelpers::getSingularFromClassNS` with `Obie\Formatters\EnglishNoun::classNameToSingular`
- Replace `Obie\Util::sendMail` with `Obie\App::sendMail`

<sup>[1]</sup> Ideally, replace all possible use of Router with Request / Response as well.

## Documentation
I want to provide documentation in the future, but currently there is not enough development time to write any documentation.

I try to write most of the code as self-documenting and minimal as possible, please try to read the code to get an idea of how to use it.

## Support
There will be no support (as in help) provided, other than fixing bugs.

This framework is in production use and is being maintained, but you will have to be on your own in using it, for now.
