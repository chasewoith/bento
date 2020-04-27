<?php
namespace Deployer;

// ------------------- DEPLOY MAGENTO TASKS ------------------- //

// Magento 2 Recipe: https://github.com/deployphp/deployer/blob/master/recipe/magento2.php

// Standard Deploy Process + 2 Magento Steps
desc('Deploy your project');
task('deploy', [
    'deploy:info',
    // run_after magento:info
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'deploy:clear_paths',
    'setup:magento',
    'deploy:db',
    'deploy:magento',
    // run_after magento:writable - Permissions Fix
    'deploy:symlink',
    // run_after nex:html - Role=Prod
    'deploy:unlock',
    'cleanup',
    'success'
]);

after('deploy:failed', 'magento:maintenance:disable');


// On Role NEW -- Install M2 via command line
desc('Setup Vanilla Magento2 Environment');
task('setup:magento', function() {
    run('cd {{release_path}} && chmod -R 777 var/ app/etc/ pub/ generated/');
    set('real_hostname', function () {
      return Task\Context::get()->getHost()->getHostname();
    });
    $host = get('real_hostname');
    run('{{bin/php}} {{release_path}}/bin/magento setup:uninstall');
    run('{{bin/php}} {{release_path}}/bin/magento setup:install --base-url=https://{{real_hostname}} --db-host={{name}}_db --db-name=dev --db-user=root --db-password={{name}}_mysql_root --backend-frontname=admin_{{name}} --admin-firstname=cool --admin-lastname=blue --admin-email=admin@coolblueweb.com --admin-user=admin --admin-password=Ba99llard99! --language=en_US --currency=USD --timezone=America/Los_Angeles --use-rewrites=1');
    run('cd {{release_path}} && chmod -R 777 var/');
    run('cd {{release_path}} && git config core.fileMode false');
    run('{{bin/php}} {{release_path}}/bin/magento deploy:mode:set developer');
})->onRoles('new');

// On Role DEMO -- Import and/or Update DB
desc('Import and/or Update DB');
task('deploy:db', function() {
    // set timeout to 60min
    $run_options['timeout'] = Deployer::setDefault('default_timeout', 3600);
    set('real_hostname', function () {
      return Task\Context::get()->getHost()->getHostname();
    });

    // Display connections listed in env.php file
    writeln(run('cd {{release_path}} && grep -r "\'connection\' => \'" ./app/etc/env.php'));

    if (askConfirmation('Would you like to import/update your database from a remote connection?')) {
        // Ask user for source and target connections
        set('source_db', ask('What source db connection would you like to pull from?','default'));
        set('target_db', ask('What target db connection would you like to push to?','default'));
        writeln('Migrating database... this may take a while.... Please Hold');

        set('slackln_text', ':pancakes: Migrating db from {{source_db}} to {{target_db}} :pancakes:');
        set('slackln_color', '#FFC73A');
        invoke('slackln');

        // Migrate stripped db from source to target
        run('cd {{release_path}} && mrun db:dump --connection={{source_db}} --strip="@development importexport_importdata" db_migration.sql',$run_options);
        run('cd {{release_path}} && mrun db:import --connection={{target_db}} db_migration.sql',$run_options);

        writeln('Successfully imported! Cleaning up 🧹');
        run('if [ -e $(echo {{release_path}}/db_migration.sql) ]; then rm {{release_path}}/db_migration.sql; fi');

        writeln('Creating Default Admin User');
        run('cd {{release_path}} && mrun admin:user:create --admin-user=admin --admin-password=Ba99llard99! --admin-email=chase@coolblueweb.com  --admin-firstname=Chase --admin-lastname=CBW');
    }

    // Check and set base url configs @TODO: Need to impliment pre check so deploy does not break if config is not set.
    if (!test('cd {{release_path}} && mrun config:show web/unsecure/base_url --no-ansi')){
        writeln(run('cd {{release_path}} && mrun config:show web/unsecure/base_url --no-ansi'));
    }

    if (!test('cd {{release_path}} && mrun config:show web/secure/base_url --no-ansi')){
        writeln(run('cd {{release_path}} && mrun config:show web/secure/base_url --no-ansi'));
    }
    

    if (askConfirmation('Would you like to update your base urls?')) {
        run('cd {{release_path}} && mrun config:store:set web/unsecure/base_url http://{{real_hostname}}/');
        run('cd {{release_path}} && mrun config:store:set web/secure/base_url https://{{real_hostname}}/');
        writeln('Urls updated!');
    }

    if (askConfirmation('Would you like to update your media urls?')) {
        $unsecure_media_url = ask('What is the unsecure media url?','http://example.com/media/');
        run('cd {{release_path}} && mrun config:store:set web/unsecure/base_media_url '.$unsecure_media_url);
        $secure_media_url = ask('What is the secure media url?','https://example.com/media/');
        run('cd {{release_path}} && mrun config:store:set web/secure/base_media_url '.$secure_media_url);
        writeln('Urls updated!');
    }

    // Show and Set Magento Deploy Mode
    $current_mode = run("if [ -x $(echo {{release_path}}/bin/magento) ]; then {{bin/php}} {{release_path}}/bin/magento deploy:mode:show; fi");
    // $current_mode = 'not working';
    $current_mode = substr($current_mode, 26, (strpos($current_mode, '.')-26));
    writeln('Current Mode: '.$current_mode);

    // show available deployment modes
    $deploy_modes = ['production','developer'];
    writeln($deploy_modes);

    // confirm set deployment mode
    $deploy_mode = get('deploy_mode');
    $deploy_mode = strtolower(ask('Deploy Mode?',$deploy_mode,$deploy_modes));
    set('deploy_mode',$deploy_mode);

    // If modes are different run deploy:mode:set new mode @TODO: Restrict modes to developer/produciton and determine new deploy path
    if ($current_mode !== $deploy_mode) {
        writeln('switching mode from '.$current_mode.' to '.$deploy_mode);
        run("if [ -x $(echo {{release_path}}/bin/magento) ]; then {{bin/php}} {{release_path}}/bin/magento deploy:mode:set ".$deploy_mode."; fi");
    }


})->onRoles('demo');

task('testing', function() {
    test('mrun config:show --help');
});

// Magento Compile
desc('Compile magento di');
task('magento:compile', function () {
    if (get('deploy_mode')!=='developer') {
        run("{{bin/php}} {{release_path}}/bin/magento setup:di:compile");
        run('cd {{release_path}} && {{bin/composer}} dump-autoload -o');
    }
});

// Magento Static-Content Deploy
desc('Deploy assets');
task('magento:deploy:assets', function () {
    if (get('deploy_mode')!=='developer') {
        run("{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy");
    }
});

// Magento Maintenance ON
desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    run("if [ -d $(echo {{deploy_path}}/current) ]; then {{bin/php}} {{deploy_path}}/current/bin/magento maintenance:enable; fi");
});

// Magento Maintenance OFF
desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    run("if [ -d $(echo {{deploy_path}}/current) ]; then {{bin/php}} {{deploy_path}}/current/bin/magento maintenance:disable; fi");
});

// Magento Setup Upgrade
desc('Upgrade magento database');
task('magento:upgrade:db', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:upgrade --keep-generated");
});

// Magento Cache Flush
desc('Flush Magento Cache');
task('magento:cache:flush', function () {
    run("{{bin/php}} {{release_path}}/bin/magento cache:flush");
});

// Deploy Magento
desc('Magento2 deployment operations');
task('deploy:magento', [
    'magento:compile',
    'magento:deploy:assets',
    'magento:maintenance:enable',
    'magento:upgrade:db',
    'magento:cache:flush',
    'magento:maintenance:disable'
])->onRoles('demo','prod');


// Role Specific Tasks


    // Permissions issues: chmod - for now
    desc('temp fix -- chmod var pub and generated');
    task('magento:writable', function(){

        if (get('deploy_mode') == 'developer') {
            run('cd {{release_path}} && rm -rf pub/static/*');
            run('cd {{release_path}} && chmod -R 777 var/ var/cache/ pub/ generated/');
        } else {
            run('cd {{release_path}} && chmod -R 777 var/ pub/ ');    
        }
        
        run('cd {{release_path}} && git config core.fileMode false');

    })->onRoles('demo');

    // After running deploy:magento run magento:writable
    after('deploy:magento', 'magento:writable');



    // Symlink html to pub for nexcess production builds
    desc('Symlink html to pub for nexcess production builds');
    task('nex:html', function(){

        cd('{{release_path}}');
        run('ln -sfn pub/ html');
        run('git config core.fileMode false');

    })->onRoles('prod');

    // Before symlinking current create symlink for html -- NEXCESS ONLY
    before('deploy:symlink', 'nex:html');

    // Before deploying Magento - check yo info
    desc('check yo info');
    task('magento:info', function() {

        // Set Variables for Magento 2
        set('shared_files', ['app/etc/env.php']);
        set('shared_dirs', ['pub/media','var/log','var/backups']); 
        // set('writable_dirs', ['var']);
        // set('clear_paths', ['var/generation/*','var/cache/*','pub/static/*']);
        
        cd('/var/www/html');
        
        writeln('There aren\'t any install scripts built for automating databases yet. Please manually import your database and update the core_config table with correct url values');

        $repo_names = [
            'ohmbeads',
            'resoul',
            'glerup-revere',
            'glerup-m2',
            'seattle-coffee-gear-m2',
            'telescopes',
            'telescopes-m2',
            'pinehurst_redesign'
        ];

        writeln($repo_names);

        // Ask for project 3 letter code
        $repo = get('repository');
        $repo = strtolower(ask('What Repo? https://github.com/coolblueweb/',$repo,$repo_names));
        if (substr($repo,0,4) !== 'http') {
            $repo = 'https://github.com/coolblueweb/'.$repo;
        }
        set('repository',$repo);

        // Set Branch
        $branch = get('branch');
        $branch = ask('What branch?',$branch);
        set('branch',$branch);


    })->onRoles('demo');

    // After running deploy:info run magento:info
    after('deploy:info', 'magento:info');