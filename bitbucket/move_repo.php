<?php

require "BitbucketUser.php";

$userName = null;
$serverName = null;
$repoName = null;
$projectKey = null;

# scan command-line for useful stuffs
$shortOpts = '';
$longOpts= array(
    "username:",
    "server:",
    "repo:",
    "key:"
);
$options = getopt($shortOpts, $longOpts);
if (isset($options['username'])) {
    $userName = $options['username'];
} else {
    echo "No username provided\n";
    usage();
    exit(1);
}
if (isset($options['server'])) {
    $serverName = $options['server'];
} else {
    echo "No server provided\n";
    usage();
    exit(1);
}
if (isset($options['repo'])) {
    $repoName = $options['repo'];
} else {
    echo "No repo slug provided\n";
    usage();
    exit(1);
}
if (isset($options['key'])) {
    $projectKey = $options['key'];
} else {
    echo "No project key provided\n";
    usage();
    exit(1);
}

if ($userName && $serverName) {
    $user = new BitbucketUser($serverName);
    $user->setUserName($userName);
} else {
    echo "Both --username|-u and --server|-s are required\n";
    exit(1);
}

# check for admin credentials
$adminUser = null;
$adminPass = null;

$adminUser = getenv('BB_ADMIN_USER', true);
$adminPass = getenv('BB_ADMIN_PASS', true);

if ($adminUser && $adminPass) {
    $credentials = "$adminUser:$adminPass";
    if ($user->retrieveRepositories($credentials)) {
        $repos = $user->getRepositories();
        foreach ($repos as $repo) {
            if ($repo['repoSlug'] == $repoName) {
                $repoURL = $serverName . "/rest/api/1.0/projects/{$user->getSlug()}/repos/{$repo['repoSlug']}";
                moveRepo($repoURL, $projectKey, $credentials);
                break;
            }
        }
    }
} else {
    echo "Bitbucket credentials not provided.\n";
    exit(1);
}

function usage()
{
    echo "Usage: move_repo.php --username <username> --server <server> --repo <repo> --key <key>\n";
    echo "\tusername - the username (owner) of the repo TO BE MOVED\n";
    echo "\tserver - the URL of the Bitbucket Server\n";
    echo "\trepo - the repository slug of the repo TO BE MOVED\n";
    echo "\tkey - the destination project key to move the repo to\n";
}

function moveRepo(string $url, string $newProject, string $creds): void
{
    # We have a repository and a project key to move it into
    $payload = "{\"project\": {\"key\": \"" . $newProject . "\"}}";
    $curl_opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERPWD => $creds,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => array(
            'Content-length: ' . strlen($payload),
            'Accept: application/json',
            'Content-type: application/json',
        ),
        CURLOPT_POSTFIELDS => $payload,
    );

    # initialize the cURL object
    $c = curl_init($url);
    curl_setopt_array($c, $curl_opts);

    # do it
    $data = curl_exec($c);
    if (curl_errno($c) !== 0) {
        echo "Error: " . curl_error($c);
    }
    curl_close($c);

    # see if we can parse the response
    $out = json_decode($data, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (json_last_error() == JSON_ERROR_NONE) {
        if (isset($out['errors'])) {
            # we have errors
            foreach ($out['errors'] as $error) {
                echo "Bitbucket API error :: {$error['message']}\n";
            }
        }
        if (isset($out['project'])) {
            $newKey = $out['project']['key'];
            if ($newKey == $newProject) {
                echo "Repository successfully moved\n";
            } else {
                echo "An error occurred while moving the repository. Please try manually.\n";
            }
        }
    } else {
        echo "Error parsing API response: " . json_last_error_msg();
    }
}