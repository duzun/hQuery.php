# Contributing

### Code style
Regarding code style like indentation and whitespace, **follow the conventions you see used in the source already.**

## Modifying the code
First, ensure that you have [Node.js](http://nodejs.org/) and [npm](http://npmjs.org/) installed.
You also need PHP 5 with php.exe in the $PATH.

1. Fork and clone the repo.
1. Run `npm install` to install all dependencies.
1. Run `npm run test-dev` to run unit tests automatically when you change a file.
1. Run `npm run apigen` to generate documentation.

Assuming that you don't see any red, you're ready to go. Just be sure to run `npm run test` after making any changes, to ensure that nothing is broken.

## Submitting pull requests

1. Create a new branch, please don't work in your `master` branch directly.
1. Add failing tests for the change you want to make while `npm run test-dev` is running.
1. Fix stuff.
1. See if the tests pass. Repeat steps 2-4 until done.
1. Update the documentation to reflect any changes.
1. Push to your fork and submit a pull request.
