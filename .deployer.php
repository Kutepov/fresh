<?php
namespace Deployer;

require 'recipe/composer.php';

set('root_path', '/data/myfresh/app2');
set('php_image', 'rcrtv/php:7.4-fpm');

host(getenv('PROD_IP'))
    ->stage('prod')
    ->user('myfresh')
    ->set('deploy_path', function() {
		return parse('{{root_path}}/data/app');
	})
    ->multiplexing(false)
    ->addSshOption('UserKnownHostsFile', '/dev/null')
    ->addSshOption('StrictHostKeyChecking', 'no');

set('repository', getenv('CI_REPOSITORY_URL'));
set('branch', 'master');
set('git_tty', false);
set('git_cache', true);
set('keep_releases', 2);
set('writable_mode', 'chmod');
set('writable_chmod_mode', '0775');
set('composer_options', 'install --no-interaction --no-progress --no-dev --ignore-platform-reqs --optimize-autoloader --no-scripts --no-plugins');

set('shared_files', [
    '.env',
    'common/config/main-local.php',
    'common/config/params-local.php',
    'api/config/main-local.php',
    'api/config/params-local.php',
    'frontend/config/main-local.php',
    'frontend/config/params-local.php',
    'backend/config/main-local.php',
    'backend/config/params-local.php',
    'console/config/main-local.php',
    'console/config/params-local.php',
    'buzz/config/main-local.php',
]);

set('shared_dirs', [
    'api/runtime',
    'api/web/img/category',
    'api/web/img/country',
    'api/web/img/source',
    'api/web/uploads/photo',
    'frontend/runtime',
    'backend/runtime',
    'buzz/web/uploads/sitemap',
    'console/runtime',
]);

set('writable_dirs', [
    'api/runtime/logs',
    'api/runtime/debug',
    'api/web/img/category',
    'api/web/img/country',
    'api/web/img/source',
    'api/web/uploads/photo',
    'frontend/runtime/logs',
    'backend/runtime/logs',
    'console/runtime/logs',
	'frontend/web/assets',
	'backend/web/assets',
    'buzz/web/uploads',
    'buzz/web/uploads/sitemap'
]);

set('bin/docker-compose', function() {
	return 'docker-compose -f {{root_path}}/docker-compose.yml';
});

set('bin/php', function() {
    return '{{bin/docker-compose}} run --rm -v ${HOME}:${HOME} -w {{release_path}} php-fpm php';
});

set('bin/composer', function() {
    return '{{bin/docker-compose}} run --rm -v ${HOME}:${HOME} -w {{release_path}} -e COMPOSER_CACHE_DIR=${HOME}/composer_cache php-fpm composer';
});

task('deploy:app:init', function() {
    run('{{bin/php}} init --env=Production --overwrite=All');
});

task('database:migrate', function() {
    run('{{bin/php}} yii migrate --interactive=0');
});

task('cache:flush-schema', function () {
    run('{{bin/php}} yii cache/flush-schema --interactive=0');
});

task('cache:flush-all', function () {
    run('{{bin/php}} yii cache/flush-all --interactive=0');
});

task('reload:php-fpm', function() {
    run('{{bin/docker-compose}} kill -s USR2 php-fpm');
});

task('restart:queue-common', function() {
    run('{{bin/docker-compose}} restart queue-common');
});

task('restart:articles-scraper', function() {
    run('{{bin/docker-compose}} ps --services | grep articles-scraper | xargs {{bin/docker-compose}} stop');
    run('{{bin/php}} yii scrapers/unlock-all-sources-urls');
    run('{{bin/docker-compose}} ps --services | grep articles-scraper | xargs {{bin/docker-compose}} up -d');
});

task('deploy:after-success', [
    'database:migrate',
    'cache:flush-schema',
    'cache:flush-all',
    'reload:php-fpm',
//    'restart:queue-common',
    'restart:articles-scraper',
]);

task('deploy:after-rollback', [
    'cache:flush-schema',
    'cache:flush-all',
    'reload:php-fpm',
//    'restart:queue-common',
    'restart:articles-scraper',
]);

after('deploy:update_code', 'deploy:app:init');
after('deploy', 'deploy:after-success');
after('rollback', 'deploy:after-rollback');
after('deploy:failed', 'deploy:unlock');
