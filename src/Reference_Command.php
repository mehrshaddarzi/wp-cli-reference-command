<?php

/**
 * Class Reference_Command
 *
 * @author Mehrshad Darzi <mehrshad198@gmail.com>
 * @package WP-CLI
 */
class Reference_Command
{
    /**
     * Developer Wordpress Url
     *
     * @var string
     */
    private static $developer_wordpress = 'developer.wordpress.org';

    /**
     * Code reference home page
     *
     * @var string
     */
    private static $reference_home_page = 'https://[url]/reference/';

    /**
     * Default Search Link Wordpress Reference
     *
     * @var string
     */
    private $wp_reference_search_link = 'https://[url]/?s=[search_word]&paged=[paged]';

    /**
     * Pagination Global $_GET in wordpress Reference
     *
     * @var string
     */
    public $pagination_global = 'paged';

    /**
     * Filter global $_GET in wordpress Reference
     *
     * @var string
     */
    private $filter_request = 'post_type';

    /**
     * Filter List Search in Wordpress Reference
     *
     * @var array
     */
    private $filter_list = array(
        'function',
        'hook',
        'class',
        'method'
    );

    /**
     * Default Disable Filter in Fetch data
     *
     * @var array
     */
    private $default_disable_filter = array('method', 'hook');

    /**
     * Filter Prefix in Url
     *
     * @var string
     */
    private $filter_prefix = 'wp-parser-';

    /**
     * Max Page Render in any request
     *
     * @var int
     */
    private $max_page_request = 25;

    /**
     * Default Dir name of Cache Reference Request
     *
     * @var string
     */
    private $dir = 'reference';

    /**
     * Full Path of Cache Dir
     *
     * @var string
     */
    private $path;

    /**
     * Last search file name
     *
     * @var string
     */
    private $last_search_file = 'last.json';

    /**
     * Last search file path
     *
     * @var string
     */
    private $last_search_file_path;

    /**
     * Number Of Max Cache Reference
     *
     * @var int
     */
    private $max_cache_reference = 1000;

    /**
     * Check Exist Pagination in Search
     *
     * @var boolean
     */
    private $is_pagination = false;

    /**
     * Pagination Dom item Key
     *
     * @var int
     */
    private $pagination_key = 0;

    /**
     * Error List
     *
     * @var array
     */
    public $log = array(
        'remove_cache'         => "Cache cleared.",
        'enter_id'             => "Please type the number list and press enter key: ",
        'enter_search_keyword' => "Please enter search keyword. e.g : 'wp reference absint'.",
        'not_found_id'         => "Your search ID is not found.",
        'empty_search_history' => "Your search history is empty.",
        'not_found'            => "Search not found in WordPress Reference.",
        'confirm_remove_cache' => "Are you sure you want to drop the cache?",
        'dom'                  => "Could not access to Wordpress Reference Page. Please try again.",
        'connect'              => "Error connecting with WordPress Reference. Please check your internet connection and try again.",
        'DOMDocument'          => "Please install/enable the DOMDocument PHP Class.\nRead More http://php.net/manual/en/dom.setup.php",
    );

    /**
     * Reference constructor.
     *
     * @param bool $cache
     * @throws \WP_CLI\ExitException
     */
    public function __construct($cache = true)
    {
        /**
         * Check Active Dom Document in php Server
         */
        if ( ! class_exists('DOMDocument')) {
            WP_CLI::error($this->log['DOMDocument']);
        }
        /**
         * Set Default Variable
         */
        $this->path                  = WP_CLI_FileSystem::path_join(WP_CLI_Helper::get_cache_dir(), $this->dir);
        $this->last_search_file_path = WP_CLI_FileSystem::path_join($this->path, $this->last_search_file);
        /**
         * Check Writable Cache dit
         */
        if ($cache) {
            $this->checkWritableCacheDir();
        }
    }

    /**
     * Remove Reference Cache.
     */
    public function runClearCache()
    {
        //Confirm Cli
        WP_CLI::confirm($this->log['confirm_remove_cache']);

        //Remove Folder
        if (WP_CLI_FileSystem::folder_exist($this->path)) {
            WP_CLI_FileSystem::remove_dir($this->path, false);
        }

        WP_CLI::success($this->log['remove_cache']);
    }

    /**
     * Show Reference in browser
     *
     * @throws \WP_CLI\ExitException
     */
    public function runBrowser()
    {
        //Check Link ID
        $json = $this->existLinkID(1, false);

        //prepare url
        if ($json != false and is_array($json) and count($json) == 1) {
            $url = $json[1];
            $this->removeLastSearchFile();
        } else {
            $url = str_replace("[url]", self::$developer_wordpress, self::$reference_home_page);
        }

        //Run Browser
        WP_CLI_Helper::Browser($url);
    }

    /**
     * Run Search Command in Wordpress Code Reference
     *
     * @param $search
     * @param array $args
     * @throws \WP_CLI\ExitException
     */
    public function runSearch($search, $args = array())
    {
        //Set Default Data
        $defaults = array(
            'allowed_filter' => false,
            'source'         => false
        );
        $arg      = WP_CLI_Util::parse_args($args, $defaults);

        //Sanitize Search Word
        $search = $this->sanitizeSearchWord($search);

        //if exist last search file remove it.
        $this->removeLastSearchFile();

        //Generate Search Link
        $url = $this->generateSearchLink($search, $arg['allowed_filter'], $paged = 1);

        //Check Local Cache
        $LocalCache = self::checkCacheURL($url);
        if ($LocalCache != false) {
            //Get Last content
            $content = $LocalCache;

            //Generate Last Link
            $last_link_data = $this->generateLastLinkFileData($content);
        } else {
            //Request Data From API
            $response = $this->fetchData($url);
            if ($response['status'] == "error") {
                WP_CLI_Helper::pl_wait_end();
                WP_CLI::error($this->log['connect']);
                exit;
            }

            /*
             * if status == redirect, This is a Single Page
             */
            if ($response['status'] == "redirect") {
                $single_page_data = $this->getSinglePageData($response['url']);
                $content          = $single_page_data['content'];
                $last_link_data   = $single_page_data['last_link_data'];
            } else {
                /*
                 * Check is 404 Not Found
                 */
                $dom = new DOMDocument;
                if (@$dom->loadHTML($response['content']) === false) {
                    WP_CLI_Helper::pl_wait_end();
                    WP_CLI::error($this->log['dom']);
                }
                if ($this->is404Page($dom) === true) {
                    WP_CLI_Helper::pl_wait_end();
                    WP_CLI::error($this->log['not_found']);
                    exit;
                }

                /*
                 * Get Search List
                 */
                //Check exist Pagination
                $this->isExistPagination($dom);

                //Get First Page Content
                $content       = $this->getSearchList($dom);
                $number_result = count($content);

                //If Page Pagination Exist
                if ($this->is_pagination === true) {
                    //Create Loop For Pagination
                    for ($paginate = 2; $paginate <= $this->max_page_request; $paginate++) {
                        //Create Page Link
                        $paged_url = $this->generateSearchLink($search, $arg['allowed_filter'], $paginate);

                        //Fetch Data
                        $result = $this->fetchData($paged_url);
                        if ($result['status'] == "data") {
                            //Get Dom Data
                            $dom = new DOMDocument;
                            @$dom->loadHTML($result['content']);

                            //Fetch this Page Data
                            $this_page_content = $this->getSearchList($dom);

                            //Push To All Item
                            foreach ($this_page_content as $key => $value) {
                                $number_result++;
                                $content[$number_result] = $value;
                            }

                            //Check if Last Page in Pagination
                            if ($this->checkIsLastSearchPage($dom) === true) {
                                break;
                            }
                        }
                    }
                }

                //Create Last Link Cache
                $last_link_data = $this->generateLastLinkFileData($content);
            }

            //Create Cache file
            if (isset($content) and ! empty($content)) {
                $this->createCacheData($url, $content);
            }
        }

        //Action end Process
        $this->endCommandProcess($content, $last_link_data, $arg);
    }

    /**
     * Get Single Page Command
     *
     * @param $url
     * @throws \WP_CLI\ExitException
     */
    private function singlePageCommand($url)
    {
        //Check Local Cache
        $LocalCache = self::checkCacheURL($url);
        if ($LocalCache != false) {
            //Get Last content
            $content = $LocalCache;

            //Generate Last Link
            $last_link_data = $this->generateLastLinkFileData($content);
        } else {
            //Request Data From API
            $response = $this->fetchData($url);
            if ($response['status'] == "error") {
                WP_CLI_Helper::pl_wait_end();
                WP_CLI::error($this->log['connect']);
                exit;
            }

            /*
             * This is a Single Page
             */
            $single_page_data = $this->getSinglePageData($url);
            $content          = $single_page_data['content'];
            $last_link_data   = $single_page_data['last_link_data'];

            //Create Cache file
            if (isset($content) and ! empty($content)) {
                $this->createCacheData($url, $content);
            }
        }

        //Action end Process
        $this->endCommandProcess($content, $last_link_data);
    }

    /**
     * Sanitize Search Word
     *
     * @param $search
     * @return string
     * @throws \WP_CLI\ExitException
     */
    private function sanitizeSearchWord($search)
    {
        //Sanitize Search Content
        $word = preg_replace('/[^a-zA-Z0-9]/', '', $search);
        if (trim($word) == "") {
            WP_CLI_Helper::pl_wait_end();
            WP_CLI::error($this->log['enter_search_keyword']);
            exit;
        } else {
            return $search;
        }
    }

    /**
     * Generate Last Link File Data
     *
     * @param $content
     * @return array
     */
    private function generateLastLinkFileData($content)
    {
        if (isset($content['structure'])) {
            //this is a alone page Method
            $last_link_data = array(1 => $content['page_url']);
        } else {
            //this is search list
            $last_link_data = array();
            foreach ($content as $k => $val) {
                $last_link_data[$k] = $val['link'];
            }
        }

        return $last_link_data;
    }

    /**
     * Action in end reference command Process
     *
     * @param $content
     * @param $last_link_data
     * @param array $options
     * @throws \WP_CLI\ExitException
     */
    private function endCommandProcess($content, $last_link_data, $options = array())
    {
        //Start Clean Cache system
        $this->removeDynamicCacheFile();

        //Create Last Link Data
        if (isset($last_link_data)) {
            WP_CLI_FileSystem::create_json_file($this->last_search_file_path, $last_link_data, false);
        }

        //Show Result
        WP_CLI_Helper::pl_wait_end();
        if (isset($content) and ! empty($content)) {
            $this->showResultCommand($content, $options);
        } else {
            WP_CLI::error($this->log['dom']);
        }
    }

    /**
     * Show Result in Command Line
     *
     * @param $content
     * @param array $options
     * @throws \WP_CLI\ExitException
     */
    private function showResultCommand($content, $options = array())
    {
        //Check is Search list or Post
        if (isset($content['structure'])) {
            //this is Single Post
            $title_color = "G";
            WP_CLI_Helper::br();

            //Structure
            if (isset($content['structure'])) {
                WP_CLI::line(WP_CLI_Helper::color("# Structure", $title_color));
                WP_CLI_Helper::br();

                // Sanitize Structure
                $exp  = explode(" ", $content['structure'] . ";");
                $word = WP_CLI_Helper::color($content['structure'], "Y");
                if (stristr($content['structure'], "(") != false) {
                    $word = '';
                    for ($i = 0; $i < count($exp); $i++) {
                        if ($i == 0) {
                            $explode = explode("( ", $exp[0]);
                            $word    .= WP_CLI_Helper::color(
                                    $explode[0] . "( ",
                                    "Y"
                                ) . WP_CLI_Helper::color($explode[1], "P");
                        } else {
                            if (stristr($exp[$i], ",")) {
                                $explode = explode(", ", $exp[$i]);
                                $word    .= WP_CLI_Helper::color(
                                        $explode[0] . ", ",
                                        "Y"
                                    ) . WP_CLI_Helper::color($explode[1], "P");
                            } else {
                                $word .= WP_CLI_Helper::color($exp[$i], "Y");
                            }
                        }
                        $word .= " ";
                    }
                }
                WP_CLI::line("  " . $word . "");
                WP_CLI_Helper::br();

                //Show Summary
                if (isset($content['summary']) and ! empty($content['summary'])) {
                    WP_CLI::line("Summary   : " . $content['summary']);
                }

                //Show Type
                if (isset($content['type']) and ! empty($content['type'])) {
                    WP_CLI::line("Reference : " . $content['type']);
                }

                //show Url
                if (isset($content['page_url']) and ! empty($content['page_url'])) {
                    WP_CLI::line("Url       : " . $content['page_url']);
                }

                //Source File
                if (isset($content['source']) and ! empty($content['source']['file'])) {
                    WP_CLI::line("Source    : " . trim($content['source']['file']) . (isset($content['source']['line']) ? ":" . trim($content['source']['line']) : ''));
                }
            }

            //Description
            if (isset($content['description']) and ! empty($content['description'])) {
                WP_CLI_Helper::br();
                WP_CLI::line(WP_CLI_Helper::color("# Description", $title_color));
                WP_CLI_Helper::br();
                WP_CLI::log(" " . $content['description']);
            }

            //Show Parameter
            if (isset($content['parameters']) and ! empty($content['parameters'])) {
                WP_CLI_Helper::br();
                WP_CLI::line(WP_CLI_Helper::color("# Parameters", $title_color));
                WP_CLI_Helper::br();

                foreach ($content['parameters'] as $k_param => $v_param) {
                    //Show Param name
                    WP_CLI::line(" " . WP_CLI_Helper::color($k_param, "Y"));

                    //Show Param Option
                    WP_CLI::line("  " . WP_CLI_Helper::color("(" . $v_param['type'] . ")", "P") . " " . WP_CLI_Helper::color("{" . $v_param['required'] . "}", "B"));

                    //Show Desc
                    WP_CLI::line("    " . $v_param['description']);

                    //Check Default Params
                    if (isset($v_param['param']) and ! empty($v_param['param'])) {
                        WP_CLI_Helper::br();
                        foreach ($v_param['param'] as $_param_k => $_param_v) {
                            WP_CLI::line("       " . $_param_k);
                            WP_CLI::line("         " . "(" . $_param_v['type'] . ")");
                            WP_CLI::line("            " . "(" . $_param_v['desc'] . ")");
                            WP_CLI_Helper::br();
                        }
                    }

                    WP_CLI_Helper::br();
                }

                //Show Return
                if (isset($content['return']) and ! empty($content['return'])) {
                    WP_CLI_Helper::br();
                    WP_CLI::line(WP_CLI_Helper::color("# Return", $title_color));
                    WP_CLI_Helper::br();
                    WP_CLI::line(" (" . trim($content['return']['type']) . ") " . trim($content['return']['desc']));
                }

                //Show Change Log
                if (isset($content['changelog']['tbody']) and ! empty($content['changelog']['tbody'])) {
                    WP_CLI_Helper::br();
                    WP_CLI::line(WP_CLI_Helper::color("# Changelog", $title_color));
                    WP_CLI_Helper::br();
                    WP_CLI_Helper::create_table($content['changelog']['tbody']);
                }
            }
        } else {
            //Search List Page
            WP_CLI_Helper::br();
            foreach ($content as $_s_key => $_s_value) {
                WP_CLI::line("{$_s_key}. " . WP_CLI_Helper::color($_s_value['title'], "Y") . " " . WP_CLI_Helper::color("[" . ucfirst($_s_value['type']) . "]", "B"));
                WP_CLI::line(WP_CLI_Helper::color("     Source: " . $_s_value['file'] . ":" . $_s_value['line'], "P"));
                WP_CLI::line("      " . $_s_value['desc']);
                WP_CLI_Helper::br();
            }
            WP_CLI_Helper::br();

            // Get ID
            WP_CLI_Util::define_stdin();
            while (true) {
                echo $this->log['enter_id'];
                $ID = fread(STDIN, 80);
                if (is_numeric(trim($ID))) {
                    break;
                }
            }
            if (isset($ID)) {
                $ID   = (int)$ID;
                $json = $this->readLastLinkFile();
                if (array_key_exists($ID, $json)) {
                    //Show Document
                    WP_CLI_Helper::pl_wait_start();
                    $this->singlePageCommand($json[$ID]);
                }
            }
        }
    }

    /**
     * Get Single Page Data
     *
     * @param $url
     * @return array
     * @throws \WP_CLI\ExitException
     */
    private function getSinglePageData($url)
    {
        //Request again For Get Data
        $result = $this->fetchData($url);

        //Check Error in connecting
        if ($result['status'] == "error") {
            WP_CLI::error($this->log['connect']);
        } else {
            //Start Dom Document
            $dom = new DOMDocument;
            if (@$dom->loadHTML($result['content']) === false) {
                WP_CLI::error($this->log['dom']);
            }

            //Get Page Data
            $content = $this->getPageDocument($dom, $url);
            return array('content' => $content, 'last_link_data' => $this->generateLastLinkFileData($content));
        }
    }

    /**
     * Generate Search Link
     *
     * @param $search
     * @param bool $allowed_filter
     * @param int $paged
     * @return mixed|string
     */
    private function generateSearchLink($search, $allowed_filter = false, $paged = 1)
    {
        //Check String $allowed_filter
        if ($allowed_filter != false and is_string($allowed_filter)) {
            $allowed_filter = array($allowed_filter);
        }

        //Create Search Link
        $link = str_ireplace("[search_word]", trim($search), $this->wp_reference_search_link);
        $link = str_ireplace("[paged]", $paged, $link);
        $link = str_ireplace("[url]", self::$developer_wordpress, $link);

        //Check Allowed filter
        if ($allowed_filter === false) {
            //Add Default All filter List
            $allowed_filter = $this->filter_list;

            //Remove Default Disable Filter
            $allowed_filter = array_diff($allowed_filter, $this->default_disable_filter);
        }

        //Add Query Parameter To Link
        foreach ($allowed_filter as $GET) {
            $link .= '&' . $this->filter_request . '[]=' . $this->filter_prefix . $GET;
        }

        return $link;
    }

    /**
     * Check Link ID exist
     *
     * @param $ID
     * @param bool $log
     * @return bool
     * @throws \WP_CLI\ExitException
     */
    private function existLinkID($ID, $log = true)
    {
        //Read Last Link file
        $json = $this->readLastLinkFile($log);

        //Check exist ID
        if ($json != false and is_array($json) and array_key_exists($ID, $json)) {
            return $json;
        } else {
            if ($log) {
                WP_CLI::error($this->log['not_found_id']);
            } else {
                return false;
            }
        }
    }

    /**
     * Read Last Link File
     *
     * @param bool $log
     * @return array|bool
     * @throws \WP_CLI\ExitException
     */
    private function readLastLinkFile($log = true)
    {
        //Check Exist Last Link File
        if ( ! file_exists($this->last_search_file_path)) {
            if ($log) {
                WP_CLI::error($this->log['empty_search_history']);
            } else {
                return false;
            }
        }

        //Read File
        $json = WP_CLI_FileSystem::read_json_file($this->last_search_file_path);
        if ($json === false) {
            WP_CLI_FileSystem::remove_file($this->last_search_file_path);
            if ($log) {
                WP_CLI::error($this->log['empty_search_history']);
            } else {
                return false;
            }
        }

        return $json;
    }

    /**
     * Check Page is contain Pagination
     *
     * @param $html_dom
     */
    private function isExistPagination($html_dom)
    {
        $nav = $html_dom->getElementsByTagName('nav');
        $i   = 0;
        foreach ($nav as $element) {
            $classy = $element->getAttribute("class");
            if (stristr($classy, "pagination") !== false) {
                $this->is_pagination  = true;
                $this->pagination_key = $i;
            }
            $i++;
        }
    }

    /**
     * Check is Last Search Page
     * @param $html_dom
     * @return bool
     */
    private function checkIsLastSearchPage($html_dom)
    {
        //If Page is not Pagination
        if ($this->is_pagination === false) {
            return false;
        }

        //Check Last Page Key
        $is_last_page   = true;
        $nav            = $html_dom->getElementsByTagName('nav');
        $pagination_dom = $nav->item($this->pagination_key)->getElementsByTagName('a');
        foreach ($pagination_dom as $html) {
            $inner_text = $html->nodeValue;
            if (stristr($inner_text, "next") !== false) {
                $is_last_page = false;
            }
        }

        return $is_last_page;
    }

    /**
     * Check 404 Page Not Found
     *
     * @param $html_dom
     * @return bool
     */
    private function is404Page($html_dom)
    {
        $is_not_found = false;
        $section      = $html_dom->getElementsByTagName('section');
        foreach ($section as $element) {
            $classy = $element->getAttribute("class");
            if (stristr($classy, "not-found") !== false) {
                $is_not_found = true;
            }
        }

        return $is_not_found;
    }

    /**
     * Convert Link To file Name
     *
     * @param $link
     * @return mixed|string
     */
    private static function convertLinkToFile($link)
    {
        //Trim Last slash
        $link = rtrim($link, "/");

        //Remove Site Url
        $link = str_ireplace(array(self::$developer_wordpress), "reference", $link);

        //Remove Protocol
        $link = str_ireplace(array("https://", "http://"), "", $link);

        //Convert Slash to line
        $link = str_ireplace("/", "---", $link);

        //Convert Search Parameter
        $link = str_ireplace("?", "+++", $link);

        return $link;
    }

    /**
     * Remove Last Search File
     */
    private function removeLastSearchFile()
    {
        if (file_exists($this->last_search_file_path)) {
            WP_CLI_FileSystem::remove_file($this->last_search_file_path);
        }
    }

    /**
     * Check Cache Url data
     *
     * @param $url
     * @return bool|array
     */
    private function checkCacheURL($url)
    {
        //convert Url to File name
        $file_name = self::convertLinkToFile($url);

        //Search in files
        $file_path = WP_CLI_FileSystem::path_join($this->path, $file_name . ".json");
        if (file_exists($file_path)) {
            return WP_CLI_FileSystem::read_json_file($file_path);
        }

        return false;
    }

    /**
     * Create Cache Data From Url
     *
     * @param $url
     * @param $data
     * @return bool
     */
    private function createCacheData($url, $data)
    {
        //convert Url to File name
        $file_name = self::convertLinkToFile($url);

        //Create Cache File data
        $file_path = WP_CLI_FileSystem::path_join($this->path, $file_name . ".json");
        if (WP_CLI_FileSystem::create_json_file($file_path, $data, false)) {
            return true;
        }

        return false;
    }

    /**
     * Remove Dynamic Cache File according to Max history file
     */
    private function removeDynamicCacheFile()
    {
        //Get list of file according to Date Create
        $list_of_cache_file = WP_CLI_FileSystem::sort_dir_by_date($this->path, "ASC");

        //Remove Last Link From list
        $list_of_cache_file = array_diff($list_of_cache_file, array($this->last_search_file));

        //Get number File in _Cache Folder
        $number_file = count($list_of_cache_file);

        //Check Max Number Cache
        if ($number_file > $this->max_cache_reference) {
            $_to_remove = $number_file - $this->max_cache_reference;
            for ($i = 0; $i < $_to_remove; $i++) {
                WP_CLI_FileSystem::remove_file(WP_CLI_FileSystem::path_join($this->path, $list_of_cache_file[$i]));
            }
        }
    }

    /**
     * Get List Of Search Page
     *
     * @param $dom
     * @return array
     */
    private function getSearchList($dom)
    {
        //Create Empty List Obj
        $result = array();

        //Check if Exist dom
        $main = $dom->getElementsByTagName('main');
        if (isset($dom) and $main->length > 0) {
            $articles = $main->item(0)->getElementsByTagName("article");
            $i        = 1;
            foreach ($articles as $a) {
                $item = array();

                //Get H1
                $h1 = $a->getElementsByTagName("h1")->item(0);

                //Get Title of Article
                $item['title'] = $h1->nodeValue;

                //Get link Of Page
                $item['link'] = $h1->getElementsByTagName("a")->item(0)->getAttribute('href');

                //Get type [List of type : function - action - filter - class - method ]
                $description  = $a->getElementsByTagName("div")->item(0)->getElementsByTagName("p")->item(0);
                $type         = $description->getElementsByTagName("b")->item(0)->nodeValue;
                $item['type'] = trim(strtolower(str_ireplace(array(":", " Hook"), "", $type)));

                //Get Description
                $desc         = $description->nodeValue;
                $exp          = explode(":", $desc);
                $item['desc'] = trim($exp[1]);

                //Source File
                $source       = $a->getElementsByTagName("div")->item(1)->getElementsByTagName("p")->item(0)->nodeValue;
                $source       = str_ireplace("Source: ", "", $source);
                $exp          = explode(":", $source);
                $item['file'] = $exp[0];
                $item['line'] = $exp[1];

                //Push To List
                $result[$i] = $item;
                $i++;
            }
        }

        return $result;
    }

    /**
     * Get Page Document
     *
     * @param $dom
     * @param $url
     * @return array|bool
     */
    private function getPageDocument($dom, $url)
    {
        $main = $dom->getElementsByTagName('main');
        if (isset($dom) and $main->length > 0) {
            $articles = $main->item(0)->getElementsByTagName("article")->item(0);
            $document = array();

            //Save Url
            $document['page_url'] = $url;

            //Type of this method
            $document['type'] = self::getTypeOfCode($url);

            //Get structure
            $document['structure'] = str_ireplace(
                "&nbsp;",
                " ",
                self::getTagContent($articles->getElementsByTagName("h1")->item(0))
            );

            //Get Summary
            $document['summary'] = self::getTagContent($articles->getElementsByTagName("section")->item(0)->getElementsByTagName("p")->item(0));

            //Check Content
            $content_dom = self::getTagsWithClass($articles->getElementsByTagName("div"), "content-toc");
            if ( ! is_null($content_dom)) {
                //Get Description
                $desc_dom = self::getTagsWithClass($content_dom->getElementsByTagName("section"), "description");
                if ( ! is_null($desc_dom)) {
                    $document['description'] = self::getTagHtml($dom->saveXML($desc_dom));
                }

                //Get Source File
                $source_dom = self::getTagsWithClass(
                    $content_dom->getElementsByTagName("section"),
                    "source-content"
                );
                if ( ! is_null($source_dom)) {
                    if (self::existTag($source_dom->getElementsByTagName("p"))) {
                        $document['source'] = array();

                        //Get Source file
                        $p_ID = 0;
                        if (trim(strtolower($source_dom->getElementsByTagName("p")->item(0)->getAttribute("class"))) == "toc-jump") {
                            $p_ID = 1;
                        }
                        $document['source']['file'] = self::removeWhitespaceWord(str_ireplace(
                            "file: ",
                            "",
                            self::getTagContent($source_dom->getElementsByTagName("p")->item($p_ID))
                        ));

                        //Get First Line
                        $gutter = self::getTagsWithClass(
                            $source_dom->getElementsByTagName("div"),
                            "source-code-container"
                        );
                        if ( ! is_null($gutter)) {
                            //Get PRE information
                            $pre                        = $gutter->getElementsByTagName("pre")->item(0)->getAttribute("class");
                            $exp_pre                    = explode("first-line:", $pre);
                            $document['source']['line'] = trim($exp_pre[1]);
                        }
                    }
                }

                //Get Parameters
                $param_dom = self::getTagsWithClass($content_dom->getElementsByTagName("section"), "parameters");
                if ( ! is_null($param_dom)) {
                    $dl_list = $param_dom->getElementsByTagName("dl");
                    if (self::existTag($dl_list)) {
                        $dt         = $dl_list->item(0)->getElementsByTagName("dt");
                        $dd         = $dl_list->item(0)->getElementsByTagName("dd");
                        $param_key  = array();
                        $param_desc = array();
                        foreach ($dt as $dt_list) {
                            $param_key[] = self::getTagContent($dt_list);
                        }
                        foreach ($dd as $dd_list) {
                            $param_array = array(
                                'type'        => 'string',
                                'required'    => 'Optional',
                                'description' => '',
                                'param'       => array()
                            );
                            $get_p       = $dd_list->getElementsByTagName("p")->item(0);

                            //Check Type
                            $type_dom = self::getTagsWithClass($get_p->getElementsByTagName("span"), "type");
                            if ( ! is_null($type_dom)) {
                                $param_array['type'] = self::removeParentheses(self::getTagContent($type_dom));
                            }

                            //Check Required
                            $require_dom = self::getTagsWithClass($get_p->getElementsByTagName("span"), "required");
                            if ( ! is_null($require_dom)) {
                                $param_array['required'] = self::removeParentheses(self::getTagContent($require_dom));
                            }

                            //Check Desc every item
                            $param_desc_dom = self::getTagsWithClass(
                                $get_p->getElementsByTagName("span"),
                                "description"
                            );
                            if ( ! is_null($param_desc_dom)) {
                                $param_array['description'] = self::removeWhitespaceWord(self::getTagContent($param_desc_dom));
                            }

                            //Check arg params
                            if (self::existTag($dd_list->getElementsByTagName("ul"))) {
                                $internal_param_dom = $dd_list->getElementsByTagName("ul")->item(0)->getElementsByTagName("li");
                                if (isset($internal_param_dom)) {
                                    $internal_param = array();
                                    foreach ($internal_param_dom as $li) {
                                        $p_name                  = trim(
                                            self::getTagContent($li->getElementsByTagName("b")->item(0)),
                                            "'"
                                        );
                                        $p_type                  = self::removeParentheses(self::getTagContent($li->getElementsByTagName("i")->item(0)));
                                        $exp_desc                = explode(
                                            "(" . $p_type . ")",
                                            self::getTagContent($li)
                                        );
                                        $internal_param[$p_name] = array(
                                            'type' => $p_type,
                                            'desc' => trim($exp_desc[1])
                                        );
                                    }

                                    //Set to Global
                                    $param_array['param'] = $internal_param;

                                    //Fixed Description
                                    $get_first_param            = key($param_array['param']);
                                    $exp_description            = explode(
                                        "'" . $get_first_param . "'",
                                        $param_array['description']
                                    );
                                    $param_array['description'] = trim($exp_description[0]);
                                }
                            }

                            //Push To array
                            $param_desc[] = $param_array;
                        }
                    }

                    //Set Parameter to Global
                    if (isset($param_key) and isset($param_desc) and count($param_key) > 0) {
                        $document['parameters'] = array();
                        for ($i = 0; $i < count($param_key); $i++) {
                            $document['parameters'][$param_key[$i]] = $param_desc[$i];
                        }
                    }
                }

                //Get Return
                $return_dom = self::getTagsWithClass($content_dom->getElementsByTagName("section"), "return");
                if ( ! is_null($return_dom)) {
                    if (self::existTag($return_dom->getElementsByTagName("p"))) {
                        $document['return']         = array();
                        $return_type                = self::getTagContent($return_dom->getElementsByTagName("p")->item(1)->getElementsByTagName("span")->item(0));
                        $document['return']['type'] = self::removeParentheses($return_type);
                        $exp_desc                   = explode(
                            $return_type,
                            self::getTagContent($return_dom->getElementsByTagName("p")->item(1))
                        );
                        $document['return']['desc'] = self::removeWhitespaceWord($exp_desc[1]);
                    }
                }

                //Get Changelog
                $changelog_dom = self::getTagsWithClass($content_dom->getElementsByTagName("section"), "changelog");
                if ( ! is_null($changelog_dom)) {
                    if (self::existTag($changelog_dom->getElementsByTagName("table"))) {
                        $document['changelog'] = self::getTableContent($changelog_dom->getElementsByTagName("table")->item(0));
                    }
                }

                //Return Complete Array
                return $document;
            }
        }

        return false;
    }

    /**
     * Fetch Data From Url
     *
     * @param $url
     * @return array|boolean
     * @throws \WP_CLI\ExitException
     */
    private function fetchData($url)
    {
        # Request Header
        $headers = array('Accept' => 'application/json');

        # Response
        $response = WP_CLI\Utils\http_request('GET', $url, null, $headers, array('timeout' => 600));
        if (200 === $response->status_code) {
            if ($response->redirects) {
                return array('status' => 'redirect', 'url' => $response->url);
            } else {
                return array('status' => 'data', 'content' => $response->body);
            }
        }
    }

    /**
     * Get Tags With class name
     *
     * @param $dom
     * @param $class
     * @return bool
     */
    private static function getTagsWithClass($dom, $class)
    {
        $i = 0;
        foreach ($dom as $element) {
            $classy = $element->getAttribute("class");
            if (stristr($classy, $class) !== false) {
                return $element;
            }
            $i++;
        }

        return null;
    }

    /**
     * Check Exist Tag
     *
     * @param $tag | $dom->getElementsByTagName( 'main' )
     * @return bool
     */
    private static function existTag($tag)
    {
        if (isset($tag) and $tag->length > 0) {
            return true;
        }

        return false;
    }

    /**
     * Show Tags Content
     *
     * @param $tag
     * @param bool $strip
     * @return string
     */
    private static function getTagContent($tag, $strip = false)
    {
        $content = @ $tag->textContent;
        if ($strip) {
            return strip_tags($content);
        }

        return $content;
    }

    /**
     * Get Html Content From Custom Dom
     *
     * @param $xml
     * @return string
     */
    private static function getTagHtml($xml)
    {
        //Remove Tags With inner Content
        $tags = array("h2", "i");
        $text = preg_replace('#<(' . implode('|', $tags) . ')(?:[^>]+)?>.*?</\1>#s', '', $xml);

        //Stripe Certain Tag
        $tags = array("code", "a", "section");
        foreach ($tags as $tag) {
            $text = self::stripSingleTag($text, $tag);
        }

        //Add line Break
        $text = preg_replace("/<\/p>/", '</p>\n\n', $text);
        $text = preg_replace("/<\/li>/", '</li>\n', $text);

        //Stripe All Tag
        $text = trim(self::removeWhitespaceWord(strip_tags($text)));

        //Trim Right \n\n
        $text = rtrim($text, '\n\n');

        return $text;
    }

    /**
     * Remove parentheses from Content
     * @param $content
     * @return string
     */
    private static function removeParentheses($content)
    {
        return str_ireplace(array("(", ")"), "", $content);
    }

    /**
     * Remove White Space From Word
     * @param $content
     * @return null|string|string[]
     */
    private static function removeWhitespaceWord($content)
    {
        return preg_replace('/\s\s+/', ' ', $content);
    }

    /**
     * Get Table Content
     * @param $tables
     * @return array
     */
    private static function getTableContent($tables)
    {
        //Create Empty object
        $tbl = array();

        //First Get th of table
        $th = $tables->getElementsByTagName('thead')->item(0)->getElementsByTagName('tr')->item(0)->getElementsByTagName('th');
        foreach ($th as $title) {
            $tbl['thead'][] = self::getTagContent($title);
        }

        //Get table Content
        $tbody = $tables->getElementsByTagName('tbody')->item(0)->getElementsByTagName('tr');
        foreach ($tbody as $row) {
            $cols        = $row->getElementsByTagName('td');
            $row_content = array();
            for ($i = 0; $i < count($tbl['thead']); $i++) {
                $row_content[$tbl['thead'][$i]] = self::removeWhitespaceWord(self::getTagContent($cols->item($i)));
            }
            $tbl['tbody'][] = $row_content;
        }

        return $tbl;
    }

    /**
     * Check Type Of Code
     *
     * @param $link
     * @return string
     * function - action - filter - class - method
     */
    private static function getTypeOfCode($link)
    {
        //Check is Hook
        if (stristr($link, "/hooks/") != false) {
            return 'Hooks';
        }

        //Check is Class or Method
        if (stristr($link, "/classes/") != false) {
            $exp       = explode("/classes/", $link);
            $exp_slash = array_filter(explode("/", $exp[1]));
            if (count($exp_slash) == 1) {
                return 'Class';
            } else {
                return 'Method';
            }
        }

        return 'Function';
    }

    /**
     * Stripe Single Tag
     *
     * @param $str
     * @param $tag
     * @return null|string|string[]
     */
    private static function stripSingleTag($str, $tag)
    {
        $str = preg_replace('/<' . $tag . '[^>]*>/i', '', $str);
        $str = preg_replace('/<\/' . $tag . '>/i', '', $str);
        return $str;
    }

    /**
     * Check Writable Cache Dir
     *
     * @throws \WP_CLI\ExitException
     */
    public function checkWritableCacheDir()
    {
        if (\WP_CLI_FileSystem::folder_exist($this->path) === false) {
            if ( ! @mkdir($this->path, 0777, true)) {
                $error = error_get_last();
                \WP_CLI_Helper::error("Failed to create directory '" . \WP_CLI_Helper::color($this->path, "Y") . "': " . \WP_CLI_Helper::color($error['message'], "R"), true);
            }
        } else {
            $_is_writable = \WP_CLI_FileSystem::is_writable($this->path);
            if ($_is_writable['status'] === false) {
                \WP_CLI_Helper::error(\WP_CLI_Helper::color($this->path, "Y") . " is not writable by current user.", true);
            }
        }
    }
}
