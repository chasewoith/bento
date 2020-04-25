<?php
namespace Deployer;

// ------------------- TUGBOAT TASKS ------------------- //

    // Build Tugboat Container
    desc('Build Tugboat Container');
    task('tug', [
        'dc_clean',
        'deploy:info',
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'tug:info',
        'deploy:update_code',
        'deploy:clear_paths',
        'deploy:symlink',
        'deploy:unlock',
        'cleanup',
        'tug:db',
        'dc_up',
        'tug:share',
        'success'
    ]);

    // --------- TUG - Pre Step 0 . What Project? --------- //
    // Set project variable for tugboat local builds
    desc('Set which project to run');
    task('what_project', function () {

        $host_names = [
            'new'
        ];

        writeln($host_names);

        // Ask for project project code
        $project = strtolower(ask('project code:','',$host_names));

        // Set variable for project
        set('project', $project);

        // Check if project directory already exists
        if (test('[ -d {{deploy_path}} ]')) {
            writeln('Project Directory Exists');
        } else {
        // Create project directory @TODO: Make recursive
        run('mkdir {{deploy_path}}');
        }

        cd('{{deploy_path}}');

    });

    // Before running tug, ask for the project code
    before('tug', 'what_project');



    // --------- TUG - Step 1. Cleanup Docker Containers --------- //
    // Docker-Compose Clean - Removes all containers and prunes networks
    desc('Run container cleanup scripts');
    task('dc_clean', function () {
        // Testing Slack Line
        set('slackln_text', ':party_dumpster_fire: _removing_ active containers and _cleaning_ network from *{{target}}*');
        set('slackln_color', '#ff0909');
        invoke('slackln');

        // If running containers run clean up scripts
        if (run('docker ps -a -q')) {
            // stop all containers
            run('docker stop $(docker ps -a -q)');
            //remove all containers
            run('docker rm $(docker ps -a -q)');
        } else { 
            writeln('no running containers'); 
        }
        // Force Prune Networks
        run('docker network prune -f');
    });


    // --------- TUG - Step 6. Get info on Previous Releases --------- //
    // Before createing tugboat environment, get basic info
    desc('set tugboat info');
    task('tug:info', function() {

        // Print List of releases
        $list = get('releases_list');
        writeln('Previous Releases');
        foreach ($list as $release) {
            writeln($release);
        }

        // If previeous Releases Exist then ask if they'd like to use previous release
        if (has('previous_release')){
            writeln('{{previous_release}}');
            $pr_ask = askConfirmation('A previous release already exists. Would you like to use this release?');

            // If yes run `docker-compose up` on current/. , unlock dep, and exit -ELSE- continue
            if ($pr_ask AND test('[ -d {{deploy_path}}/current/ ]')) {
                //
                writeln('Great! Running docker-compose up ...');
                cd('{{deploy_path}}/current/ ]');
                run('docker-compose up -d');
                invoke('deploy:unlock');
                invoke('success');
                exit(0);
            } else {
                writeln('Running a new release...');
            } 
        }

    });

    // --------- TUG - Step 12. Tug Db from previous release --------- //
    // Docker-Compose Up - Starts Docker container for project
    desc('Pull a db into a tugboat container');
    task('tug:db', function () {
        writeln('{{deploy_path}}');
        run('ls -la {{deploy_path}}/current/');
        // Check to see if there are previous releases, If so Ask if they'd like to pull the db from the source
        if (has('previous_release')){
            writeln('{{previous_release}}');

            // Ask if user would like to pull db from latest release
            if (askConfirmation('Would you like to pull the db from previous release?')) {
                writeln('Great! Moving db Directory from {{previous_release}}...');
                run("if [ -d $(echo {{previous_release}}/var/lib/mysql) ]; then mkdir {{deploy_path}}/current/var && mkdir {{deploy_path}}/current/var/lib/ && mv {{previous_release}}/var/lib/mysql {{deploy_path}}/current/var/lib/.; fi");
            } else {
                writeln('REMEMBER TO IMPORT A DATABASE');
            }
        } else {
            // TODO: Pull from remote source -- Can be accomplished on application level deployment step.
            writeln('No previous releases were found. Please remember to import dbs manually as needed');
        }

    });


    // --------- TUG - Step 13. Docker Compose UP --------- //
    // Docker-Compose Up - Starts Docker container for project
    desc('docker-compose up for selected project');
    task('dc_up', function () {
        
        // Check if project directory exists
        if (test('[ -h {{deploy_path}}/current ]')) {
            cd('{{deploy_path}}/current');
        } else {
            invoke('deploy:unlock');
            invoke('deploy:failed');
            exit(0);
        }

        // Ask if user would like to pull new Tugboat images
        if (askConfirmation('Would you like to pull new Tugboat images? y/n')) {
            run('docker-compose pull');
        } else {
            writeln('Pulling from cache (if available)');
        } 

        // Docker Compose up
        run('docker-compose up -d --build');

        // Complete
        writeln('Setup Complete');
    });


    // --------- TUG - Step 14. Move Shared Directory into Container --------- //
    // Add shared files
    desc('Create and add shared files');
    task('tug:share', function () {

        // Check to see if there are previous releases, If so Ask if they'd like to pull the shared directory from the source
        if (has('previous_release')){
            writeln('{{previous_release}}');

            // Ask user if they would like to pull shared directory from previous release

            if (askConfirmation('Would you like to pull shared directory from previous release?')) {
                writeln('Great! Moving Shared Directory from {{previous_release}}...');
                run("if [ -d $(echo {{previous_release}}/var/www/html/shared) ]; then mv {{previous_release}}/var/www/html/shared {{deploy_path}}/current/var/www/html/.; fi");
            } else {
                writeln('Okay Fine..');
                run('if [ -d $(echo {{deploy_path}}/current/shared) ]; then mv {{deploy_path}}/current/shared {{deploy_path}}/current/var/www/html/.; fi');
            }
        } else {
            // New Release - Pull Shared directory into container
            run('if [ -d $(echo {{deploy_path}}/current/shared) ]; then mv {{deploy_path}}/current/shared {{deploy_path}}/current/var/www/html/.; fi');
        }

        // set www-data ownership on #ISSUE - Setting ownership to 33 from outside the container
        // run('chown -R www-data:www-data {{deploy_path}}/current/var/www/html/');
        // run('chmod -R 775 {{deploy_path}}/current/var/www/html/');
              
    });
  

    //Cleanup Task
    // desc('Cleanup Scripts');
    // task('clean', [
    //     'clean:docker',
    //     'clean:known',
    //     'clean:sites'
    // ]);
