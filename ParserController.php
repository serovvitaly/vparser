<?php defined('VS_PARSER_CONTROLLER') or die('Permission denied');

/**
 * Парсер - тут добавить нечего
 * 
 */
class VsParser_Controller {
    
    /**
     * Стек результатов
     */
    protected $_results_stack   = array();
    
    /**
     * Стек правил обработки
     */
    protected $_rules_stack     = array();
    

    /**
     * Внутренний счетчик вызова функции - обработчика результата
     */
    protected $_handler_counter = 0;
    
    
    /**
     * Максимальное число вызовов функции - обработчика результата
     */
    public $_max_handler_call = 10;
    

    public function __construct()
    {
        //
    }
    
    /**
     * Геттер
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }
    
    /**
     * Сеттер
     */
    public function __set($name, $value)
    {
        if (isset($this->$name)) {
            $this->$name = $value;
            return $this;
        }
        
        return false;
    }
    

    /**
     * Основная функция реализации парсинга. 
     * Производит последовательную выборку правил из 
     * стека, ищет соответствующие этим правилам паттерны 
     * и производит парсинг.
     * Полученный ресультат помещает в стек результатов.
     */
    public function run($rule_name = NULL)
    {
        $_rules_collection = array();
        
        if ($rule_name AND is_string($rule_name)) {
            if (isset($this->_rules_stack[$rule_name]) AND $this->_rules_stack[$rule_name] instanceof VsParser_Rule) {
                $_rules_collection[] = $this->_rules_stack[$rule_name];
            }
        }
        
        elseif ($rule_name AND is_array($rule_name)) {
            if (count($rule_name) > 0) {
                foreach ($rule_name AS $_r_name) {
                    if (isset($this->_rules_stack[$_r_name]) AND $this->_rules_stack[$_r_name] instanceof VsParser_Rule) {
                        $_rules_collection[] = $this->_rules_stack[$_r_name];
                    }
                }
            } else {
                return false;
            }
        }
        
        elseif (!$rule_name) {
            if (count($this->_rules_stack) > 0) {
                $_rules_collection = $this->_rules_stack;
            }    
        }
        
        else {
            return false;
        }
        
        if (count($_rules_collection) > 0) {
            foreach ($_rules_collection AS $_rule) {
                $this->_parse($_rule);
            }
        } else {
            return false;
        }
        
        
        return $this;
    }
    
    
    /**
     * Добавляет правило выполнения и обработки 
     * результата парсинга 
     */
    public function add_rule($rule_name, $handler)
    {
        if (empty($rule_name) OR empty($handler)) {
            return false;
        }
        
        
        $_rule = new VsParser_Rule($rule_name, $handler);
        
        $this->_rules_stack[$rule_name] = $_rule;
        
        return $_rule;
    }
    
    
    /**
     * Возвращает объект правила с заданным именем
     */
    public function get_rule($rule_name)
    {        
        if (isset($this->_rules_stack[$rule_name])) {
            return $this->_rules_stack[$rule_name];
        }
        
        return false;
    }
    
    
    /**
     * Парсит одно правило
     */ 
    protected function _parse(VsParser_Rule $rule)
    {
        $handler = $rule->get_handler();
        
        $address_list = $rule->get_address_list()->as_array();
        
        $pattern_list = $rule->get_pattern_list()->as_array();
        
        if (count($address_list) <= 0 OR count($address_list) <= 0) {
            return false;
        }
        
        $data = array();
        
        foreach ($address_list AS $url_key => $url) {
            
            $complited_list = $rule->_address->complited_list();
            
            
            if ( !in_array($url, $complited_list) ) {
                
                $content = $this->_get_content($url);
                
                
                //$content = str_replace("\n", '', $content);
                //$content = str_replace(" ", '', $content);
                //$content = iconv('utf-8', 'windows-1251', $content);
                
                if (count($pattern_list) > 0) {
                    foreach ($pattern_list AS $name => $pattern) {
                        
                        @preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
                        
                        $data[$name][] = array(
                            'url' => $url,
                            'result' => $matches
                        );
                    }
                }
                
                $rule->_address->complet($url);
                
            }
            
        }
        
        $result = new VsParser_Result();
        
        $result->data    = $data;
        
        $result->rule    = $rule;
        
        $result->parser  = $this;
        
        if (count($data) > 0 AND function_exists($handler)) {
            call_user_func($handler, $result);
        }        
    }
    
    
    protected function _get_content($url)
    {
        $content = file_get_contents($url);
        
        return $content;
    }

}


/**
 * Реализует правило выполнения парсинга и обработки результата
 */
class VsParser_Rule {

    protected $_name;

    protected $_pattern;
    
    protected $_address;
    
    protected $_handler;
    
    /**
     * Статус правила:
     * 0 - правило только создано и не поступало в обработку
     * 1 - 
     * 2 -
     * 3 -
     */
    protected $_status = 0;
    
    
    
    /**
     * Создает правило выполнения и обработки 
     * результата парсинга 
     */
    public function __construct($rule_name, $handler)
    {
        $this->_name    = $rule_name;
        
        $this->_handler = $handler;
        
        $this->_address = new VsParser_Address_Collection();
        
        $this->_pattern = new VsParser_Pattern_Collection();
        
    }

    
    /**
     * Геттер
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }
    
    
    /**
     * Сеттер
     */
    public function __set($name, $value)
    {
        if (isset($this->$name)) {
            $this->$name = $value;
            return $this;
        }
        
        return false;
    }
    
    
    /**
     * Добавляет адрес в стек URL-адресов
     */
    public function add_address($url, $clearing = false)
    {
        if ($clearing === true) {
            $this->clear_address();
        }
        
        $this->_address->add($url);
        
        return $this;
    }
    
    
    /**
     * Очищает стек URL-адресов
     */
    public function clear_address()
    {
        $this->_address->clear();
        
        return $this;
    }
    
    
    /**
     * Добавляет паттерн в коллекцию паттернов
     */
    public function add_pattern()
    {
        $func_args = func_get_args();
        
        if (count($func_args) < 1) {
            return false;
        }
        
        // елси на входе один многомерный массив
        if (is_array($func_args[0])) {
            if (count($func_args[0]) > 0) {
                foreach ($func_args[0] AS $_pattarn_name => $_pattern_mix) {
                    $_pattern_mix = "/$_pattern_mix/Uis";
                    $this->_pattern->add($_pattarn_name, $_pattern_mix);
                }
            } else {
                return false;
            }
        }
        
        // если на входе первая строка, второй - массив или строка
        elseif (is_string($func_args[0]) AND isset($func_args[1]) AND is_array($func_args[1])) {
            if (!empty($func_args[0]) AND count($func_args[1]) > 0) {
                $this->_pattern->add($func_args[0], $func_args[1]);
            } else {
                return false;
            }
        }
        
        else {
            return false;
        }
        
        return $this;
    }
    
    
    /**
     * Добавляет обработчик результата парсинга
     */
    public function add_handler()
    {
        
    }
    
    
    /**
     * Возвращает имя пользовательской функции-обработчика
     */
    public function get_handler()
    {
        return $this->_handler;
    }
    
    
    /**
     * Возвращает коллекцию адресов
     */
    public function get_address_list()
    {
        return $this->_address;
    }    
    
    
    /**
     * Возвращает коллекцию паттернов
     */
    public function get_pattern_list()
    {
        return $this->_pattern;
    }
    
    
    /**
     * Повышает статус на еденицу
     * (статус нельзя изменять непосредственно, 
     * а лишь последовательно повышать его статус)
     */
    protected function _status_up()
    {
        $this->_status++;
    }
}


/**
 * Реализует объект результата выполнения парсинга
 */
class VsParser_Result {

    public $data;

    public function __construct()
    {
        //
    }
}


/**
 * Коллекция URL-адресов
 */
class VsParser_Address_Collection {
    
    protected $_collection = array();
    
    protected $_completeds = array();
    
    public function __construct()
    {
        //
    }
    
    
    /**
     * Возвращает список адресов в виде массива
     */
    public function as_array()
    {
        return $this->_collection;
    }
    
    
    /**
     * Добавляет URL-адрес в коллекцию адресов
     */
    public function add($url)
    {
        if (!in_array($url, $this->_collection)) {
            $this->_collection[] = $url;
        }
        
        return $this;
    }
    
    
    /**
     * Добавляет адрес в список обработанных адресов
     */
    public function complet($url)
    {        
        if (!in_array($url, $this->_completeds)) {
            $this->_completeds[] = $url;
            unset($this->_collection[$url]);
        }
        
        return $this;
    }
    
    
    /**
     * Возвращает список обработанных адресов
     */
    public function complited_list()
    {
        return $this->_completeds;
    }
    
    
    /**
     * Очищает коллекцию URL-адресов
     */
    public function clear()
    {
        $this->_collection = array();
        $this->_completeds = array();
        
        return $this;
    }
    
}

/**
 * Коллекция паттернов
 */
class VsParser_Pattern_Collection {
    
    protected $_collection = array();
    
    public function __construct()
    {
        //
    }
    
    
    /**
     * Возвращает список адресов в виде массива
     */
    public function as_array()
    {
        return $this->_collection;
    }
    
    
    /**
     * Добавляет паттерн в коллекцию паттернов
     */
    public function add($name, $pattern)
    {
        if (!in_array($pattern, $this->_collection)) {
            $this->_collection[$name] = $pattern;
        }
        
        return $this;
    }
}