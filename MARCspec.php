<?php
/**
* MARCspec is the specification of a reference, encoded as string, to a set of data from within a MARC record.
* 
* @author Carsten Klee <mailme.klee@yahoo.de>
* @package CK\MARCspec
* @copyright For the full copyright and license information, please view the LICENSE 
* file that was distributed with this source code.
*/
namespace CK\MARCspec;

use CK\MARCspec\Exception\InvalidMARCspecException;

/**
* Class to decode, validate and encode MARC spec as string.
* For Specification of MARC spec as string see
* <http://cklee.github.io/marc-spec/marc-spec.html>
*/
class MARCspec implements MARCspecInterface, \JsonSerializable{

    /**
    * @var Field The field object
    */
    private $field;

    /**
    * @var array Array of subfields
    */
    private $subfields = [];
    
    /**
    * {@inheritdoc}
    * 
    * @throws InvalidMARCspecException
    */ 
    public function __construct($spec)
    {
    #print "Construct MS $spec \n";
        if($spec instanceof FieldInterface)
        {
            $this->field = $spec;
        }
        else
        {
            $this->checkIfString($spec);
            $spec = trim($spec);
            $specLength = strlen($spec);
            // check string length
            if(3 > $specLength)
            {
                throw new InvalidMARCspecException(
                    InvalidMARCspecException::MS.
                    InvalidMARCspecException::LENGTH.
                    InvalidMARCspecException::MINIMUM3,
                    $spec
                );
            }
            if(preg_match('/\s/', $spec))
            {
                throw new InvalidMARCspecException(
                    InvalidMARCspecException::MS.
                    InvalidMARCspecException::SPACE,
                    $spec
                );
            }
            
            /**
             * $specMatches[0] => whole spec 
             * $specMatches[1] => fieldspec
             * $specMatches[2] => rest
             */ 
            if(0 === preg_match('/^([^{$]*)(.*)/',$spec,$specMatches))
            {
                throw new InvalidMARCspecException(
                    InvalidMARCspecException::MS.
                    InvalidMARCspecException::UNKNOWN,
                    $spec
                );
            }
            
            
            // creates a fieldspec
            if(!empty($specMatches[1]))
            {
                
                $this->field = new Field($specMatches[1]);
            }
            else
            {
                 throw new InvalidMARCspecException(
                    InvalidMARCspecException::MS.
                    InvalidMARCspecException::MISSINGFIELD,
                    $spec
                );
            }

            // process rest
            if(!empty($specMatches[2]))
            {
            #print "Construct calling parseRef: ".$specMatches[2]."\n";
                $this->createInstances($this->parseDataRef($specMatches[2]));
            }
        }
        #print "MS created $this\n";
    }
    
    /**
     * {@inheritdoc}
     */
    public static function setField(FieldInterface $field)
    {
        return new MARCspec($field);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getField()
    {
        return $this->field;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSubfields()
    {
        return (0 < count($this->subfields)) ? $this->subfields : null;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @throws \UnexpectedValueException if argument length is higher than 1
     */
    public function getSubfield($arg)
    {
        if(1 < strlen($arg))
        {
            throw new \UnexpectedValueException('Method only allows argument to be 1 character long. Got '. strlen($arg));
        }
        $_subfields = null;
        if(0 < count($this->subfields))
        {
            foreach($this->subfields as $subfield)
            {
                if( $subfield->getTag() == $arg ) $_subfields[] = $subfield; 
            }
            return $_subfields;
        }
        
        return null;
    }
    /**
     * {@inheritdoc}
     * 
     * @throws InvalidMARCspecException
     * 
     */
    public function addSubfields($subfields)
    {
        if($subfields instanceof SubfieldInterface)
        {
            $this->subfields[] = $subfields;
        }
        else
        {
            $this->checkIfString($subfields);
            if(2 > strlen($subfields))
            {
                throw new InvalidMARCspecException(
                    InvalidMARCspecException::SF.
                    InvalidMARCspecException::LENGTH.
                    InvalidSubfieldspecException::MINIMUM2,
                    $subfields
                );
            }
            if('$' !== $subfields[0])
            {
                throw new InvalidMARCspecException(
                    InvalidMARCspecException::SF.
                    InvalidSubfieldspecException::PREFIX,
                    $subfields
                );
            }
            #print "AddSubfields calling parseRef: ".$subfields."\n";
            $this->createInstances($this->parseDataRef($subfields));
        }
    }
    

    
    /**
    * Parses subfield ranges into single subfields
    *
    * @internal
    *
    * @param string $arg The assumed subfield range
    * 
    * @return array $_range[string] An array of subfield specs
    * 
    * @throws InvalidMARCspecException
    */
    private function handleSubfieldRanges($arg)
    {
        $argLength = strlen($arg);
        if($argLength !== 3) 
        {
            throw new InvalidMARCspecException(
                InvalidMARCspecException::SF.
                InvalidMARCspecException::LENGTH3,
                $arg
            );
        }
        elseif(preg_match('/[a-z]/', $arg[0]) && !preg_match('/[a-z]/', $arg[2]))
        {
            throw new InvalidMARCspecException(
                InvalidMARCspecException::SF.
                InvalidMARCspecException::RANGE,
                $arg
            );
        }
        elseif(preg_match('/[A-Z]/', $arg[0]) && !preg_match('/[A-Z]/', $arg[2]))
        {
            throw new InvalidMARCspecException(
                InvalidMARCspecException::SF.
                InvalidMARCspecException::RANGE,
                $arg
            );
        }
        elseif(preg_match('/[0-9]/', $arg[0]) && !preg_match('/[0-9]/', $arg[2]))
        {
            throw new InvalidMARCspecException(
                InvalidMARCspecException::SF.
                InvalidMARCspecException::RANGE,
                $arg
            );
        }
        else
        {
            foreach(range($arg[0],$arg[2]) as $sfStep)
            {
                $_range[] = '$'.$sfStep;
            }
            return $_range;
        }
    }
    

    


    /**
     * checks if argument is a string
     * 
     * @internal
     * 
     * @param string $arg The argument to check
     * 
     * @throws \InvalidArgumentException if the argument is not a string
     */
    private function checkIfString($arg)
    {
        if(!is_string($arg)) throw new \InvalidArgumentException("Method only accepts string as argument. " .gettype($arg)." given.");
    }
    

    /**
    * Detects and creates subfields and subspecs
    *
    * @param string $arg string of subfieldspecs and/or subspecs
    * 
    * @return array $_detected Associative array of instances of Subfield and SubSpec
    * 
    * @throws InvalidMARCspecException
    */
    public static function parseDataRef($arg)
    {
    #print "parseRef: $arg\n";
        $open = 0;
        $close = 0;
        $_nocount = ['$','\\'];
        $_detected = [];
        $subfieldCount = 0;
        $subSpecCount = 0;
        for($i = 0;$i < strlen($arg);$i++)
        {
            if($open === $close)
            {
                if(0 < $i)
                {
                    if('{' == $arg[$i] && !in_array($arg[$i-1],$_nocount))
                    {
                        $open++;
                    }
                    if('}' == $arg[$i] && !in_array($arg[$i-1],$_nocount))
                    {
                        throw new InvalidMARCspecException(
                            InvalidMARCspecException::MS.
                            InvalidMARCspecException::BRACKET,
                            $arg
                        );
                    }
                    if('$' == $arg[$i] && !in_array($arg[$i-1],$_nocount))
                    {
                        $subfieldCount++;
                    }
                }
                else // 0 == $i
                {
                    if('{' == $arg[$i])
                    {
                        $open++;
                    }
                    if('}' == $arg[$i])
                    {
                        throw new InvalidMARCspecException(
                            InvalidMARCspecException::MS.
                            InvalidMARCspecException::BRACKET,
                            $arg
                        );
                    }
                }
                
                if($open !== $close)
                {
                    if(array_key_exists('subspec',$_detected))
                    {
                        if(array_key_exists($subSpecCount,$_detected[$subfieldCount]['subspec']))
                        {
                            $subspec = $_detected[$subfieldCount]['subspec'][$subSpecCount].$arg[$i];
                            $_detected[$subfieldCount]['subspec'][$subSpecCount] = $subspec;
                        }
                        else
                        {
                            $_detected[$subfieldCount]['subspec'][] = $arg[$i];
                        }
                    }
                    else
                    {
                        $_detected[$subfieldCount]['subspec'][] = $arg[$i];
                    }
                }
                else
                {
                    $subSpecCount = 0;
                    if(array_key_exists($subfieldCount,$_detected))
                    {
                        $spec = $_detected[$subfieldCount]['subfield'].$arg[$i];
                        $_detected[$subfieldCount]['subfield'] = $spec;
                    }
                    else
                    {
                        $_detected[] = ['subfield'=>$arg[$i]];
                    }
                }
            }
            else // open != close
            {
                
                if('{' == $arg[$i] && !in_array($arg[$i-1],$_nocount))
                {
                    $open++;
                }
                if('}' == $arg[$i] && !in_array($arg[$i-1],$_nocount))
                {
                    $close++;
                }
                
                $subspec = $_detected[$subfieldCount]['subspec'][$subSpecCount].$arg[$i];
                $_detected[$subfieldCount]['subspec'][$subSpecCount] = $subspec;
                
                if($open === $close) $subSpecCount++;
                
            }
        }
        if($open !== $close)
        {
            throw new InvalidMARCspecException(
                InvalidMARCspecException::MS.
                InvalidMARCspecException::BRACKET,
                $arg
            );
        }
        return $_detected;
    }
    
    /**
     * Creates instances of Subfield and Subspec
     * 
     * @param array $_detected Associative array of subfields and subspecs
     */ 
    private function createInstances($_detected)
    {
    #print "createInstances";
    #print_r($_detected);
    #print "\n";
        foreach($_detected as $key => $_dataRef)
        {
            if(array_key_exists('subfield',$_dataRef))
            {
                if(2 == strpos($_dataRef['subfield'],'-')) // assuming subfield range
                {
                    $_subfields = $this->handleSubfieldRanges(substr($_dataRef['subfield'],1));
                }
                else
                {
                    $_subfields[] = $_dataRef['subfield'];
                }
                foreach($_subfields as $subfield)
                {
                    $Subfield = new Subfield($subfield);
                    $this->subfields[] = $Subfield;
                    if(array_key_exists('subspec',$_dataRef)) 
                    {
                        foreach($_dataRef['subspec'] as $subspec)
                        {
                            #print "creating SubSpec $subspec\n";
                            $_Subspecs = $this->createSubSpec($subspec,$Subfield);
                            $Subfield->addSubSpec($_Subspecs);
                        }
                    }
                }
            }
            else
            {
                foreach($_dataRef['subspec'] as $subKey => $subspec)
                {
                    $Subspec = $this->createSubSpec($subspec);
                    $this->field->addSubSpec($Subspec);
                }
            }
        }
    }
    
    /**
     * Creates SubSpecs
     * 
     * @internal
     *
     * @param string $assumedSubspecs A string with assumed subSpecs
     * 
     * @return SubSpecInterface|$_subSpec[SubSpecInterface] Instance of SubSpecInterface
     * or numeric array of instances of SubSpecInterface
     * 
     * @throws InvalidMARCspecException
     */
    private function createSubSpec($assumedSubspecs,$Subfield=null)
    {
        $context = $this->field->getBaseSpec();
        if(!is_null($Subfield))
        {
            $context .= $Subfield->getBaseSpec();
        }
        #$context = "$this"; // object in string context
        $_nocount = ['$','\\'];
        $_operators = ['?','!','~','='];
        $specLength = strlen($assumedSubspecs);
#print "create SubSpec ".$context.$assumedSubspecs."\n";
        $_subTermSets = preg_split('/(?<!\\\\)\|/', substr($assumedSubspecs,1,$specLength-2));
        
        foreach($_subTermSets as $key => $subTermSet)
        {
            if(preg_match('/(?<![\\\\\$])[\{\}]/',$subTermSet,$_error, PREG_OFFSET_CAPTURE))
            {
                throw new InvalidMARCspecException(
                    InvalidMARCspecException::MS.
                    InvalidMARCspecException::ESCAPE,
                    $assumedSubspecs
                );
            }
            
            $leftSubTerm = null;
            $rightSubTerm = null;
            $operator = null;
            $subTermSetLength = strlen($subTermSet);
            $_subTermSet = null;
            
            for($i = 0; $i<$subTermSetLength;$i++)
            {
                $previous = (0 < $i) ? $i-1 : 0;
                if(in_array($subTermSet[$i],$_operators) && !in_array($subTermSet[$previous],$_nocount))
                {
                    $operator .= $subTermSet[$i];
                    $pos = $i;
                    $len = strlen($operator);
                }
            }
            
            if(!is_null($operator))
            {
                $operatorStartPos = $pos + 1 - $len;
                $_subTermSet['leftSubTerm'] = substr($subTermSet,0,$operatorStartPos); // might be empty
                $_subTermSet['rightSubTerm'] = substr($subTermSet,$pos+1);
                if(empty($_subTermSet['rightSubTerm']))
                {
                    throw new InvalidMARCspecException(
                        InvalidMARCspecException::SS.
                        InvalidMARCspecException::MISSINGRIGHT,
                        $subTermSet
                    );
                }
            }
            else
            {
                $operator = '?';
                $_subTermSet['rightSubTerm'] = $subTermSet;
                $_subTermSet['leftSubTerm'] = null;
            }

            foreach($_subTermSet as $subTermKey => $subTerm)
            {
            
                if(!empty($subTerm))
                {
                    if('\\' == $subTerm[0]) // is a comparisonString
                    {
                        $_subTermSet[$subTermKey] = new ComparisonString(substr($subTerm,1));
                    }
                    else
                    {
                        switch($subTerm[0]) 
                        {
                            case '[':
                            case '/':
                            case '_':
                            case '$':
                                if(is_null($context))
                                {
                                    throw new InvalidMARCspecException(
                                        InvalidMARCspecException::SS.
                                        InvalidMARCspecException::MISSINGFIELD,
                                        $assumedSubspecs
                                    );
                                }
                                if($refPos = strrpos($context,$subTerm[0]))
                                {
                                    $_subTermSet[$subTermKey] = new MARCspec(substr($context,0,$refPos).$subTerm);
                                }
                                else
                                {
                                    $_subTermSet[$subTermKey] = new MARCspec($context.$subTerm);
                                }
                            break;
                            default: $_subTermSet[$subTermKey] = new MARCspec($subTerm);
                        }
                    }
                }
                else
                {
                    $_subTermSet[$subTermKey] = new MARCspec($context);
                }
            }
            $_subSpec[$key] = new SubSpec($_subTermSet['leftSubTerm'],$operator,$_subTermSet['rightSubTerm']);
        }
        return (1 < count($_subSpec)) ? $_subSpec : $_subSpec[0];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize() 
    {
        $_marcSpec['field'] = $this->field->jsonSerialize();
        
        foreach($this->subfields as $subfield)
        {
            $_marcSpec['subfields'][] = $subfield->jsonSerialize();
        }
        return $_marcSpec;
    }
    
    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $marcspec = "$this->field";
        foreach($this->subfields as $subfield)
        {
            $marcspec .= "$subfield";
        }
        return $marcspec;
    }
} // EOC
