<?php

/**
 * Asynchronous job for saving URL and tags to database.
 *
 * @category    Aoe
 * @package     Aoe_Static
 * @author      Jan Papenbrock <j.papenbrock@gruenspar.de>
 */
class Aoe_Static_Model_Job_UrlTags extends Mns_Resque_Model_Job_Abstract
{
    /**
     * Write given URL and its tags into database for future cache purging.
     *
     * @return void
     */
    public function perform()
    {
        if (!isset($this->args['url']) || !isset($this->args['tags'])) {
            return;
        }

        /** @var Aoe_Static_Model_Cache $cache */
        $cache->saveUrlTags($this->args['url'], $this->args['tags']);
    }
}
