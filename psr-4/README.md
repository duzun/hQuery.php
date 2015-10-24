# PSR-4 Autoloading

The files in this folder are intended for projects supporting PSR-4 (PHP >= 5.3.0).

If you project runs on PHP <= 5.2.0, use `../hquery.php` instead.

Main module `../hquery.php` creates namespaced aliases for hQuery classes on first invocation.
The same effect could be achieved exclusively through files in this folder, but I found
that `$obj instanceof duzun\hQuery\Element` evaluates to false when `duzun\hQuery\Element`
is not yet declared/loaded.

