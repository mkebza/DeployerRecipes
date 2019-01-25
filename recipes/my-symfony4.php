<?php
/**
 * Created by PhpStorm.
 * User: mkebza
 * Date: 2019-01-25
 * Time: 08:56
 */

namespace Deployer;

require 'recipe/symfony4.php';

// Extra tasks
task('deploy:cronjobs', function () {
    $cronJobKey = get('cron_job_key');

    $newDefinition = sprintf(
        "# %s - BEGIN\n%s\n# %s - END",
        $cronJobKey,
        implode("\n", array_map(function($job) {
            if (is_string($job)) {
                return $job;
            }

            if (is_array($job)) {
                return sprintf(
                    '%s `which php` %s/current/bin/console %s %s',
                    $job[0], // timer
                    get('deploy_path'),
                    $job[1], // command
                    isset($job[2]) ? ' # '.$job[2] : ''
                );
            }

            fail ('Invalid job record, either string or array with 2-3 elements required');
        }, get('cron_jobs'))),
        $cronJobKey
    );

    $actualContent = run('crontab -l');

    $matchRe = sprintf("@(# %s - BEGIN.*# %s - END)@sm", preg_quote($cronJobKey, '@'), preg_quote($cronJobKey, '@'));
    if (preg_match($matchRe, $actualContent, $matches)) {
        $actualContent = str_replace($matches[1], $newDefinition, $actualContent);
    } elseif (count(get('cron_jobs')) > 0) {
        $actualContent .= "\n\n".$newDefinition;
    }

    run('echo "' . $actualContent . '" | crontab -');
});

// Tasks
task('js:yarn-install-vendors', 'yarn install');
task('js:build', 'yarn run build');
task('deploy:reload-php-fpm', 'sudo systemctl reload php7.2-fpm.service');


// Defautl configuration
set('default_timeout', 900);
set('git_tty', true);
set('cron_job_key', get('application'));
set('cron_jobs', []);
set('allow_anonymous_stats', false);
set('keep_releases', 3);


// Process
after('deploy:failed', 'deploy:unlock');

// Other functions
function get_deploy_tasks() {
    $taskOrder = [
        'deploy:info',
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'deploy:shared',
        'deploy:vendors',
        'deploy:writable',
        'deploy:cache:clear',
        'deploy:cache:warmup',
        'database:migrate',
        'deploy:symlink',
        'deploy:cronjobs',
        'deploy:reload-php-fpm',
        'deploy:unlock',
        'cleanup',
    ];

    if (has('enable_build') && get('enable_build')) {
        array_splice($taskOrder, 10, 0, [
            'js:yarn-install-vendors',
            'js:build',
        ]);
    }

    return $taskOrder;
}
