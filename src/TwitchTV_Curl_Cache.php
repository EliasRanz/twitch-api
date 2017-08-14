<?php
namespace TwitchTV\TwitchTV_Curl_Cache;

/**
 * Class TwitchTV_Curl_Cache
 * @package TwitchTV\TwitchTV_Curl_Cache
 * @author Robarelli
 */
class TwitchTV_Curl_Cache {
    private $_cache = array();

    /**
     * Retrieve the cache data
     * @param string $id    The cache id
     * @return mixed|null
     */
    protected function get_data($id) {
        return array_key_exists($id, $this->_cache) ? $this->_cache[$id] : null;
    }

    /**
     * Set the cache data
     * @param string $id    The cache id
     * @param $data
     * @return mixed
     */
    protected function set_data($id, $data) {
        return $this->_cache[$id] = $data;
    }

    /**
     * Remove an item from the cache
     * @param string $id    The cache id
     */
    protected function unset_data($id) {
        unset($this->_cache[$id]);
    }

    /**
     * Checks if a key has been set in the cache
     * @param string $id    The cache id
     * @return bool
     */
    protected function data_exists($id) {
        return !empty($this->_cache[$id]);
    }
}