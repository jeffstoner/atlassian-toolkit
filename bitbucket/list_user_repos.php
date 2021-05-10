<?php

require "BitbucketUser.php";

$userName = null;
$serverName = null;

# scan command-line for useful stuffs
$shortOpts = "u:s:";
$longOpts= array(
    "username:",
    "server:"
);
$options = getopt($shortOpts, $longOpts);
# LongOpts override shortOpts
if (isset($options['u'])) {
    $userName = $options['u'];
}
if (isset($options['username'])) {
    $userName = $options['username'];
}
if (isset($options['s'])) {
    $serverName = $options['s'];
}
if (isset($options['server'])) {
    $serverName = $options['server'];
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
    if ($user->retrieveRepositories("$adminUser:$adminPass")) {
        $repos = $user->getRepositories();
        echo "Repositories for user {$user->getUserName()}\n";
        foreach ($repos as $repo) {
            echo "{$repo['repoName']} ({$repo['repoSlug']}): {$repo['repoHref']}\n";
            if (isset($repo['parentRepoHref'])) {
                echo "\t=> Forked from {$repo['parentRepoName']} ({$repo['parentRepoSlug']}): {$repo['parentRepoHref']}\n";
            }
        }
    }
} else {
    echo "Bitbucket credentials not provided.\n";
    exit(1);
}
