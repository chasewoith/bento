<?php
namespace Deployer;

// ------------------- DEPLOY MAGENTO 1 ------------------- //

    // WooCommerce Recipe
    task('deploy:woo', [
        'deploy:info',
        'woo:info',
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'wpengine',
        'deploy:shared',
        'deploy:writable',
        'deploy:clear_paths',
        'woo:writable',
        'woo:symlink',
        'deploy:symlink',
        'deploy:unlock',
        'cleanup',
        'success'
    ]);

    task('wpengine', function(){
        //Sync wpengine locally
        if (askConfirmation('Would you like to import/update from WP-Engine?')) {

            //Ask user for backup zip
            cd('{{release_path}}');
            set('backup_zip_filename', ask('ZIP filename: ',''));
            run('wget https://snappyshot-wpengine-download.s3.amazonaws.com/zip-archives/{{backup_zip_filename}} && unzip -o {{backup_zip_filename}}');
            writeln('Downloading and Unzipping backup...');
            run('rm {{backup_zip_filename}}');

            //Write ssh config file line by line
            set('idFile', ask('identity filename: ',''));
            run('echo "Host git.wpengine.com" > ~/.ssh/config');
            run('echo "   User git" >> ~/.ssh/config');
            run('echo "   User git" >> ~/.ssh/config');
            run('echo "   Hostname git.wpengine.com" >> ~/.ssh/config');
            run('echo "   PreferredAuthentications publickey" >> ~/.ssh/config');
            run('echo "   IdentityFile ~/.ssh/{{idFile}}" >> ~/.ssh/config');
            run('echo "   IdentitiesOnly yes" >> ~/.ssh/config');

            //Git add production
            set('prodEnvName', ask('Production Env Name: ','prodenvname'));
            run('git remote add production git@git.wpengine.com:production/{{prodEnvName}}');
            set('stageEnvName', ask('Staging Env Name: ','stageenvname'));
            run('git remote add staging git@git.wpengine.com:staging/{{stageEnvName}}');
            run('rm -rf wp-content/mu-plugins && git reset --hard');

            //Import Database
            // if (test('[ -e wp-content/mysql.sql ]') && askConfirmation('Would you like to import a DB from sql backup?')) {
                // run('mysql -u admin --password={{project}}_mysql_admin dev < wp-content/mysql.sql');
            // } else {writeln('Make sure to update your DB');}
        }
    })->onRoles('wp-engine');

    task('woo:info', function(){
        //set shared directories
        set('shared_files', []);
        set('shared_dirs', []);
        // set('writable_dirs', ['var']);
        // set('clear_paths', ['var/generation/*','var/cache/*','pub/static/*']);
        
        cd('/var/www/html');
        
        writeln('There aren\'t any install scripts built for automating databases yet. Please manually import your database and update the core_config table with correct url values');

        $repo_names = ['wordpress','magetwo'];

        writeln($repo_names);

        // Ask for project 3 letter code
        $repo = get('repository');
        $repo = strtolower(ask('What Repo? https://github.com/chasewoith/',$repo,$repo_names));
        if (substr($repo,0,4) !== 'http') {
            $repo = 'https://github.com/chasewoith/'.$repo;
        }
        set('repository',$repo);

        // Set Branch
        $branch = get('branch');
        $branch = ask('What branch?',$branch);
        set('branch',$branch);

    });

    // Set open Permissions for xxx directories and set git filemode to false
    task('woo:writable', function(){
        // run('cd {{release_path}} && chmod -R 777 var/ media/ ');
        run('cd {{release_path}} && git config core.fileMode false && git config --global user.email "chase.woith@gmail.com" && git config --global user.name "chasewoith"');
    });

    // Symlink wp-config.php to env.php
    task('woo:symlink', function(){
        cd('{{release_path}}');
        run('ln -sfn {{deploy_path}}/shared/app/etc/env.php wp-config.php');
        run('ln -sfn {{deploy_path}}/shared/uploads wp-content/uploads');
    });