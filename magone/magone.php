<?php
namespace Deployer;

// ------------------- DEPLOY MAGENTO 1 ------------------- //

    // Magento 1 Recipe
    task('deploy:m1', [
        'deploy:info',
        // run_after magento:info
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'deploy:shared',
        // 'deploy:vendors',
        'deploy:writable',
        'deploy:clear_paths',
        // 'setup:magento',
        // 'deploy:magento',
        // run_after magento:writable - Permissions Fix
        'm1:writable',
        'm1:symlink',
        'deploy:symlink',
        // run_after nex:html - Role=Prod
        'deploy:unlock',
        'cleanup',
        'success'
    ]);

    // Set open Permissions of var and media directories
    task('m1:writable', function(){
        run('cd {{release_path}} && chmod -R 777 var/ media/ ');
        run('cd {{release_path}} && git config core.fileMode false');
    });

    // Symlink local.xml to env.php and set git filemode to false
    task('m1:symlink', function(){
        cd('{{release_path}}/app/etc/');
        run('ln -sfn env.php local.xml');
    });