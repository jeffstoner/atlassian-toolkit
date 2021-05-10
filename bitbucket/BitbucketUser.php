<?php

class BitbucketUser
{
    protected string $userName;
    protected string $slug;
    protected array $repositories;
    protected string $server;

    /**
     * BitbucketUser constructor.
     * @param string|null $server
     * @throws \BadMethodCallException
     */
    public function __construct(string $server = null)
    {
        if ($server === null || $server === '') {
            throw new \BadMethodCallException("Invalid Server");
        }

        $this->server = $server;
    }

    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return array
     */
    public function getRepositories(): array
    {
        return $this->repositories;
    }

    /**
     * @param string $userName
     */
    public function setUserName(string $userName): void
    {
        $this->userName = mb_strtolower($userName);
        $this->slug = '~' . $this->userName;
    }

    /**
     * Call the Bitbucket API to get the user's repositories
     * @param string $credentials
     * @throws \BadMethodCallException
     * @return bool
     */
    public function retrieveRepositories(string $credentials = ''): bool
    {
        if ($credentials === null || $credentials === '') {
            throw new \BadMethodCallException("Invalid credentials");
        }

        $success = true;
        $curl_opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $credentials,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        # Build the full path to the user's repositories
        $url = $this->server . '/rest/api/1.0/projects/' . $this->slug . '/repos';

        # initialize the cURL object
        $c = curl_init($url);
        curl_setopt_array($c, $curl_opts);

        # go get it
        $data = curl_exec($c);

        # check explicitly for false
        if ($data === false) {
            echo "cURL error: " . curl_error($c) . "\n";
            curl_close($c);
            return false;
        }

        try {
            $this->repositories = $this->parseRepositories($data);
        } catch (\Exception | \BadMethodCallException $exception) {
            echo $exception->getMessage();
            $success = false;
        }

        return $success;
    }

    /**
     * Parse a chunk of text into an array of repository data
     * @param string $chunk
     * @return array
     * @throws \Exception
     * @throws \BadMethodCallException
     */
    private function parseRepositories(string $chunk = ''): array
    {
        if ($chunk === '') {
            throw new \BadMethodCallException("Invalid data chunk");
        }

        $data = json_decode($chunk, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON data chunk');
        }

        # parse the data we got from Bitbucket
        if (isset($data['errors'])) {
            # API returned an error message
            foreach ($data['errors'] as $error) {
                echo $error['message'];
            }
            return [];
        }

        # How many repos do we have
        $repos = [];
        if ($data['size'] > 0) {
            foreach ($data['values'] as $repo) {
                unset($repoData);
                # discover some things about this repo
                $repoData = array(
                    'repoName' => $repo['name'],
                    'repoSlug' => $repo['slug'],
                    'repoProjectKey' => $repo['project']['key'],
                    'repoProjectName' => $repo['project']['name'],
                    'repoHref' => $repo['links']['self'][0]['href'],
                );
                if (isset($repo['origin'])) {
                    # This was forked from another repo

                    $repoData['parentProjectKey'] = $repo['origin']['project']['key'];
                    $repoData['parentProjectName'] = $repo['origin']['project']['name'];
                    $repoData['parentRepoSlug'] = $repo['origin']['slug'];
                    $repoData['parentRepoName'] = $repo['origin']['name'];
                    $repoData['parentRepoHref'] = $repo['origin']['links']['self'][0]['href'];
                }

                $repos[] = $repoData;
            }
        }

        return $repos;
    }
}