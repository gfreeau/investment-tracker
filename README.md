A tool to keep track of investments such as index funds.

This tool will read a configuration file and retrieve stock data from Yahoo Finance and display it in a table.

See `config/config.yml.dist`, `config/portfolio.yml.dist` and `config/rebalance.yml.dist`  for example configuration files.

The tool runs on the command line only, run it by executing `php app.php -p config/portfolio.yml`.

You can also define a rebalance configuration and see what the changes to your portfolio will be by executing `php app.php -p config/portfolio.yml -r config/rebalance.yml`.

It requires php7.
