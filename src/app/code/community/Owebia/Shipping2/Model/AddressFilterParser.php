<?php
/**
 * Copyright © 2008-2020 Owebia. All rights reserved.
 * See COPYING.txt for license details.
 */

class Owebia_Shipping2_Model_AddressFilterParser
{
    protected $_configParser;
    protected $_input;
    protected $_length;
    protected $_position;
    protected $_bufferStart;
    protected $_char;
    protected $_join;

    protected $_output = '';
    protected $_level;
    protected $_parentLevel;
    protected $_regexp = false;
    protected $_litteral = false;
    protected $_litteralQuote;
    protected $_callbackMap = [
        '(' => 'openingParenthesisCallback',
        ')' => 'closingParenthesisCallback',
        '"' => 'quoteCallback',
        "'" => 'quoteCallback',
        ' ' => 'spaceCallback',
        '-' => 'hyphenCallback',
        ',' => 'commaCallback',
        '/' => 'slashCallback',
    ];

    public function __construct($configParser)
    {
        $this->_configParser = $configParser;
    }

    public function parse($input)
    {
        $this->_input = $input;
        $this->_length = strlen((string)$this->_input);
        // look at each character
        $this->_join = ' && ';
        for ($this->_position = 0; $this->_position < $this->_length; $this->_position++) {
            $this->_char = $this->_input[$this->_position];
            if (isset($this->_callbackMap[$this->_char])) {
                $this->{$this->_callbackMap[$this->_char]}();
            } else {
                $this->defaultCallback();
            }
        }
        $this->push($this->buffer());
        return $this->_output;
    }

    protected function closingParenthesisCallback()
    {
        if ($this->_regexp || $this->_litteral) {
            return;
        }
        $this->push($this->buffer() . ')');
        $this->_parentLevel = null;
    }

    protected function openingParenthesisCallback()
    {
        if ($this->_regexp || $this->_litteral) {
            return;
        }
        $this->push($this->buffer());
        $this->push($this->_join, $onlyIfNotEmpty = true);
        $this->push('(');
        $this->_parentLevel = $this->_level;
        $this->_join = ' && ';
    }

    protected function quoteCallback()
    {
        if (!$this->_litteral || $this->_litteralQuote == $this->_char) {
            $this->_litteral = !$this->_litteral;
            $this->_litteralQuote = $this->_char;
        }
        if ($this->_bufferStart === null) {
            $this->_bufferStart = $this->_position;
        }
    }

    protected function spaceCallback()
    {
        if ($this->_regexp || $this->_litteral) {
            return;
        }
        $this->push($this->buffer());
    }

    protected function hyphenCallback()
    {
        if ($this->_regexp || $this->_litteral) {
            return;
        }
        $this->push($this->buffer());
        $this->_join = ' && !';
    }

    protected function commaCallback()
    {
        if ($this->_regexp || $this->_litteral) {
            return;
        }
        $this->push($this->buffer());
        $this->push(' || ');
    }

    protected function slashCallback()
    {
        $this->_regexp = !$this->_regexp;
        $this->defaultCallback();
    }

    protected function defaultCallback()
    {
        if ($this->_bufferStart === null) {
            $this->_bufferStart = $this->_position;
        }
    }

    protected function escapeString($input)
    {
        return $this->_configParser->escapeString($input);
    }

    protected function buffer()
    {
        if ($this->_bufferStart !== null) {
            // extract string from buffer start to current position
            $buffer = substr($this->_input, $this->_bufferStart, $this->_position - $this->_bufferStart);
            // clean buffer
            $this->_bufferStart = null;
            // throw token into current scope
            if ($buffer == '*') {
                $buffer = 1;
            } else if ($this->_parentLevel == 'country') {
                if (preg_match('/^[A-Z]{2}$/', $buffer)) {
                    $buffer = "{{c}}==={$this->escapeString($buffer)}";
                    $this->_level = 'country';
                } else if (strpos($buffer, '/') === 0
                    && ($buffer[strlen($buffer) - 1] == '/'
                        || substr($buffer, strlen($buffer) - 2, 2) == '/i'
                    )
                ) {
                    $buffer = "preg_match('" . str_replace("'", "\\'", $buffer) . "', (string)({{p}}))";
                } else if (strpos($buffer, '*') !== false) {
                    $buffer = "preg_match('/^"
                        . str_replace(
                            ["'", '*'],
                            ["\\'", '(?:.*)'],
                            $buffer
                        )
                        . "$/', (string)({{p}}))";
                } else if (preg_match('/^"[^"]+"$/', $buffer)) {
                    $buffer = trim($buffer, '"');
                    $buffer = "({{p}}==={$this->escapeString($buffer)} || {{r}}==={$this->escapeString($buffer)})";
                } else if (preg_match('/^\'[^\']+\'$/', $buffer)) {
                    $buffer = trim($buffer, "'");
                    $buffer = "({{p}}==={$this->escapeString($buffer)} || {{r}}==={$this->escapeString($buffer)})";
                } else {
                    $buffer = "({{p}}==={$this->escapeString($buffer)} || {{r}}==={$this->escapeString($buffer)})";
                }
            } else if (preg_match('/^[A-Z]{2}$/', $buffer)) {
                $buffer = "{{c}}==={$this->escapeString($buffer)}";
                $this->_level = 'country';
            }
            return $buffer;
        }
        return null;
    }

    protected function push($text, $onlyIfNotEmpty = false)
    {
        if (isset($text)) {
            if (!$onlyIfNotEmpty || $this->_output) {
                $this->_output .= $text;
            }
        }
    }
}