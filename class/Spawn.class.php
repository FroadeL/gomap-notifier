<?php
/**
 * Spawn Class
 *
 */
class Spawn {

    // Configuration file name.
    private $configFile = 'config.json';

    // Data collected from API.
    private $data;
    // First run encounterId check. (bool)
    private $firstRunEid = false;
    // First run for gymId check. (bool)
    private $firstRunGid = false;
    // Configuration. (object)
    private $config;
    // Max encounterId. (string)
    private $maxeid;
    // Max gymId. (string)
    private $maxgid;
    // Ignore list. (array)
    private $ignoreList;

    /**
     * Default constructor.
     */
    public function __construct()
    {
        // First get the configuration.
        $this->getConfiguration();

        // Get the max encounterId.
        $this->getMaxEid();

        // Get the max gymId.
        $this->getMaxGid();

        // Get the global ignore list.
        $this->getIgnoreList();
    }

    /**
     * Get configuration.
     */
    private function getConfiguration()
    {
        // Get names from json file and set var.
        $this->config = json_decode(file_get_contents($this->configFile));
    }

    /**
     * Get max encounter id.
     */
    private function getMaxEid()
    {
        // Get max encounter id from txt file.
        $maxeid = file_get_contents($this->config->file->maxeid);

        // Id found.
        if (!empty($maxeid)) {
            $this->maxeid = $maxeid;

        // First run detected.
        } else {
            // Set to zero.
            $this->maxeid = 0;

            // Mark this as first run. (no message send)
            $this->firstRunEid = true;
        }

    }

    /**
     * Get max gym id.
     */
    private function getMaxGid()
    {
        // Get max gym id from txt file.
        $maxgid = file_get_contents($this->config->file->maxgid);

        // Id found.
        if (!empty($maxgid)) {
            $this->maxgid = $maxgid;

        // First run detected.
        } else {
            // Set to zero.
            $this->maxgid = 0;

            // Mark this as first run. (no message send)
            $this->firstRunGid = true;
        }

    }

    /**
     * Update max encounter id.
     */
    private function updateMaxEid()
    {
        // Write max encounter id to txt file.
        file_put_contents($this->config->file->maxeid, $this->maxeid);
    }

    /**
     * Update max gym id.
     */
    private function updateMaxGid()
    {
        // Write max gym id to txt file.
        file_put_contents($this->config->file->maxgid, $this->maxgid);
    }

    /**
     * Get global ignore list.
     */
    private function getIgnoreList()
    {
        // Get names from json file and set var.
        $this->ignoreList = json_decode(file_get_contents($this->config->file->ignoreList));
    }

    /**
     * Get url by curl.
     * @param $url
     * @return mixed
     */
    private function curl($url)
    {
        $ch = curl_init();

        // Optional: Proxy support.
        curl_setopt($ch, CURLOPT_PROXY, "proxy2");
        curl_setopt($ch, CURLOPT_PROXYPORT, '8080');

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0); // Don't return headers.
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $data = curl_exec($ch);

        curl_close($ch);

        return json_decode($data);
    }

    /**
     * Build url.
     * @return string
     */
    private function buildUrl()
    {
        $array = array(
            'mid' => $this->maxeid,
            'gid' => $this->maxgid,
            'ex'  => '[' . implode(",", $this->ignoreList) . ']',
            'w'   => $this->config->map->boundWest,
            'e'   => $this->config->map->boundEast,
            'n'   => $this->config->map->boundNorth,
            's'   => $this->config->map->boundSouth
        );

        // Build query.
        $query = http_build_query($array);

        // Build url.
        $url = $this->config->map->url . '/mnew.php?' . $query;

        return $url;
    }

    /**
     * Get data.
     */
    public function getData()
    {
        // Build url.
        $url = $this->buildUrl();

        // Get data by curl.
        $this->data = $this->curl($url);
    }

    /**
     * Get mons.
     * @return array
     */
    public function getMons()
    {
        // Init empty mons array.
        $mons = array();

        // Any notification method must be enabled.
        if ($this->config->telegram->active == true || $this->config->discord->active == true) {

            // Pokemon found.
            if (!empty($this->data) && !empty($this->data->pokemons)) {
                // Iterate each pokemon.
                foreach ($this->data->pokemons AS $pokemon) {
                    // Find max encounter id.
                    if ($pokemon->eid > $this->maxeid) {
                        $this->maxeid = $pokemon->eid;
                    }

                    // Don't collect mons on the first run.
                    if (!$this->firstRunEid) {
                        // Only use pokemon with IV value.
                        //if (!empty($pokemon->iv)) {
                            // Calculate real iv.
                            //$pokemon->iv = round($pokemon->iv * 100 / 45);

                            // Push into mons array.
                            array_push($mons, $pokemon);
                        //}
                    }
                }

                // Write last encounter id to file.
                $this->updateMaxEid();
            }
        }

        // Return them.
        return $mons;
    }

    /**
     * Get gyms.
     * @return array
     */
    public function getGyms()
    {
        $timestamp = time();

        //echo $timestamp;

        // Init empty gyms array.
        $gyms = array();

        // Any notification method must be enabled.
        if ($this->config->telegram->active == true || $this->config->discord->active == true) {

            // Gym found.
            if (!empty($this->data) && !empty($this->data->gyms)) {

                $lastGid = $this->maxgid;

                // Iterate each gym.
                foreach ($this->data->gyms AS $gym) {
                    // Find max gym id. (timestamp is gymId)
                    if ($gym->ts > $this->maxgid) {
                        $this->maxgid = $gym->ts;
                    }

                    // Don't collect gyms on the first run.
                    if (!$this->firstRunGid) {
                        // TODO: Change level to >= 4 when legendaries are not that common someday.
                        // Raid detected. Min. level 5.
                        if (!empty($gym->lvl) && $gym->lvl > 4) {
                            // Raid wasn't found before.
                            if ($gym->ts > $lastGid) {
                                // Raid is not over.
                                if ($timestamp < $gym->re) {
                                    // Push into gyms array.
                                    array_push($gyms, $gym);
                                }
                            }
                        }
                    }
                }

                // Write last gym id to file.
                $this->updateMaxGid();
            }
        }

        // Return them.
        return $gyms;
    }
}