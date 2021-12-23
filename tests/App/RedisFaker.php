<?php

// https://www.digitalocean.com/community/tutorials/getting-started-with-redis-in-php

namespace Touhidurabir\RequestResponseLogger\Tests\App;


class RedisFaker {

    /**
     * Fake redis top level storage
     *
     * @var array
     */
    protected $storage;


    /**
     * Fake redis configuration
     *
     * @var array
     */
    protected $configs;


    /**
     * Create a new instance
     *
     * @param  array $config
     * @return void
     */
    public function __construct(array $configs = []) {

        $this->configs = $configs;
        $this->storage = [];
    }

    /**
     * Get the fake redis connection instance itself
     *
     * @param  array $config
     * @return self
     */
    public function connection(array $configs = []) : self {

        $this->configs = $configs;

        return $this;
    }


    /**
     * Store data at the beginning of list in fake redis storage for specified key
     *
     * @param  string $key
     * @param  mixed  $value
     * 
     * @return int
     */
    public function lpush(string $key, $value) : int {

        array_unshift($this->getStoreByKey($key), $value);

        return $this->llen($key);
    }


    /**
     * Pull data from the beginning of list in fake redis storage for specified key
     *
     * @param  string $key
     * @return mixed
     */
    public function lpop(string $key) {

        if ( $this->llen($key) <= 0 ) {

            return false;
        }

        return array_shift($this->getStoreByKey($key));
    }


    /**
     * Store data at the end of list in fake redis storage for specified key
     *
     * @param  string $key
     * @param  mixed  $value
     * 
     * @return int
     */
    public function rpush(string $key, $value) : int {

        array_push($this->getStoreByKey($key), $value);

        return $this->llen($key);
    }


    /**
     * Pull data from the end of list in fake redis storage for specified key
     *
     * @param  string $key
     * @return mixed
     */
    public function rpop(string $key) {

        if ( $this->llen($key) <= 0 ) {

            return false;
        }

        return array_pop($this->getStoreByKey($key));
    }


    /**
     * Get the current lenght of list in fake redis storage for specified key
     *
     * @param  string $key
     * @return int
     */
    public function llen(string $key) : int {

        return count($this->getStoreByKey($key));
    }


    /**
     * Get the range of data from the list in fake redis storage for specified key
     *
     * @param  string   $key
     * @param  int      $start
     * @param  int      $end
     * 
     * @return array
     */
    public function lrange(string $key, int $start, int $end) : array {

        if ( $end < 0 ) {

            return $end === -1 
                ? $this->getStoreByKey($key) 
                : array_slice($this->getStoreByKey($key), $start, $end+1);
        }

        return array_slice($this->getStoreByKey($key), $start, $end+1);
    }


    /**
     * Remove specified range of data from the list in fake redis storage for specified key
     *
     * @param  string   $key
     * @param  int      $elementsCount
     * @param  int      $direction
     * 
     * @return bool
     */
    public function ltrim(string $key, int $elementsCount, int $direction) : bool {

        // remove first $elementsCount elements from the beginning of list
        if ( $elementsCount > 0 && $direction < 0 ) {

            array_splice($this->getStoreByKey($key), 0, $elementsCount);
        }

        // remove first $direction elements from the end of list
        if ( $elementsCount <= 0 && $direction < 0 ) {

            array_splice($this->getStoreByKey($key), -5);	
        }

        return true;
    }


    /**
     * Get the all stored data/values of list in fake redis storage for specified key
     *
     * @param  string $key
     * @return int
     */
    public function get(string $key) : array {

        return $this->getStoreByKey($key);
    }


    /**
     * Get the reference of specific storage in the list specified by key
     *
     * @param  string $key
     * @return int
     */
    protected function &getStoreByKey(string $key) : array {
        
        if ( !array_key_exists($key, $this->storage) ) {

            $this->storage[$key] = [];
        }

        return $this->storage[$key];
    }

}
