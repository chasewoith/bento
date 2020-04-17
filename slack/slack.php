<?php
namespace Deployer;
use Deployer\Utility\Httpie;

// ------------------- SLACK TASKS ------------------- //

    // Default Values
    set('slackln_color', '#000');
    set('slackln_title', function () {
        return '{{project}}';
    });
    set('slackln_text', 'message text');


    // Send a message to Slack
    desc('Send a message to Slack');
    task('slackln', function () {

        if (!get('slack_webhook', false)) {
            return;
        }

        $attachment = [
            'title' => get('slackln_title'),
            'text' => get('slackln_text'),
            'color' => get('slackln_color'),
            'mrkdwn_in' => ['text'],
        ];

        Httpie::post(get('slack_webhook'))->body(['attachments' => [$attachment]])->send();

    })
        ->once()
        ->shallow();


    // Notify Slack when running tug
    desc('Notify Slack when running tug');
    task('tug:notify', function () {

        if (!get('slack_webhook', false)) {
            return;
        }

        set('slackln_color', '#4d91f7');
        set('slackln_title', function () {
            return '{{project}}';
        });
        set('slackln_text', ':tugboat: _{{user}}_ tugging `{{branch}}` to *{{target}}* ');

        $attachment = [
            'title' => get('slackln_title'),
            'text' => get('slackln_text'),
            'color' => get('slackln_color'),
            'mrkdwn_in' => ['text'],
        ];

        Httpie::post(get('slack_webhook'))->body(['attachments' => [$attachment]])->send();

    })
        ->once()
        ->shallow();