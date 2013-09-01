<?php
namespace Mystem;

/**
 * Class Word
 * @property array[] $variants lexical interpretation variants:
 *  - string $normalized - normalized word representation
 *  - boolean $strict - dictionary or predictable normalized representation
 *  - array $grammems - lexical information (constants from MystemConst)
 */
class Word
{
    /**
     * @var string $grammemeRegexp cached constants regular expression from MystemConst
     */
    private static $grammemeRegexp = null;

    /* @var string $constructorClass need for instantiate properly class in newFrom* methods */
    protected static $constructorClass = '\Mystem\Word';

    /**
     * @var string $original original string
     */
    public $original;

    public $variants = array();

    public function __construct()
    {
        if (self::$grammemeRegexp === null) {
            self::$grammemeRegexp = '#('.implode('|', MystemConst::grammemeList()).')#u';
        }
    }

    /**
     * @param string $lexicalString - prepared string from Mystem
     * @param int $maxVariants
     * @return Word
     */
    public static function newFromLexicalString($lexicalString, $maxVariants = null)
    {
        /* @var Word $word */
        $word = new static::$constructorClass();
        $word->parse($lexicalString, $maxVariants);
        return $word;
    }

    /**
     * @param string $word
     * @param int $maxVariants
     * @return Word
     */
    public static function stemm($word, $maxVariants = null)
    {
        $lexicalString = Mystem::stemm($word);
        return self::newFromLexicalString($lexicalString[0], $maxVariants);
    }

    /**
     * Normalized word
     * @return string
     */
    public function normalized()
    {
        if (isset($this->variants[0]['normalized'])) {
            return $this->variants[0]['normalized'];
        } else {
            return '';
        }
    }

    public function __toString(){
        return $this->normalized();
    }

    /**
     * @param string $lexicalString - prepared string from Mystem
     * @param int $maxVariants
     */
    protected function parse($lexicalString, $maxVariants = null)
    {
        $counter = 0;
        $this->original = mb_substr($lexicalString, 0, mb_strpos($lexicalString, '{'));
        $variants = explode('|', mb_substr($lexicalString, mb_strlen($this->original) + 1, -1));
        foreach ($variants as $text)
        {
            preg_match('#^(?P<normalized>[^=]*)=(?P<grammems>.*)$#u', $text, $match);
            $variant['normalized'] = !empty($match['normalized']) ? $match['normalized'] : $this->normalized();
            if (mb_strrpos($variant['normalized'], '?')) {
                $variant['strict'] = false;
                $variant['normalized'] = mb_substr($variant['normalized'], 0, -1);
            } else {
                $variant['strict'] = true;
            }

            if (!empty($match['grammems'])) {
                $gramm = strtr($match['grammems'], '=,', '  ');
                preg_match_all(self::$grammemeRegexp, $gramm, $match);
                if (!empty($match[0]))
                    $variant['grammems'] = $match[0];
            } else {
                $variant['grammems'] = array();
            }
            $this->variants[$counter++] = $variant;
            if ($maxVariants!==null && $counter>=$maxVariants)
                break;
        }
    }

    /**
     * Search grammese primitive in word variants
     * @param $gramm - grammar primitive from MystemConst
     * @param null $level - variants maximum depth
     * @return boolean
     */
    public function checkGrammeme($gramm, $level = null){
        $counter = 0;
        foreach ($this->variants as $variant) {
            if (in_array($gramm, $variant['grammems'])) {
                return true;
            } elseif ($level!==null && ++$counter>=$level) {
                return false;
            }
        }
        return false;
    }

    /**
     * return null | MystemConst::PRESENT | MystemConst::PAST | MystemConst::FUTURE
     */
    public function getVerbTime($variant = 0)
    {
        if (!isset($this->variants[$variant])) {
            return null;
        }

        foreach (array(MystemConst::PRESENT, MystemConst::FUTURE, MystemConst::PAST) as $time) {
            if (in_array($time, $this->variants[$variant]['grammems'])) {
                return $time;
            }
        }

        return null;
    }

}