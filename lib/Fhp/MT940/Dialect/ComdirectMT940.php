<?php

namespace Fhp\MT940\Dialect;

use Fhp\MT940\MT940;

class ComdirectMT940 extends MT940
{
    public const DIALECT_ID = 'https://fints.comdirect.de/fints';

    public function extractStructuredDataFromRemittanceLines($descriptionLines, string &$gvc, array &$rawLines, array $transaction): array
    {
        // comdirect will send a series of lines that can also contain
        // TYPE, EREF, MREF, CRED and BREF in the SVWZ fields (raw 20-29 
        // and 60ff), e.g. 
        // [20] => LASTSCHRIFT / BELASTUNG
        // [21] => 01.01.9999-31.01.9999-GRUND
        // [22] => /01.01.2
        // [23] => 025-31.01.9999-GRUND/01.01.
        // [24] => 9999-31.
        // [25] => 01.9999-VZ BE/01.01.9999-31
        // [26] => .01.9999
        // [27] => -VZ HE/
        // [28] => END-TO-END-REF.:
        // [29] => 9999999999 MV 9999-0007-001
        // [30] => 
        // [31] => 
        // [32] => BAYERISCHE VERSORGUNGSKAMME
        // [33] => R BAY. VERSORGUNGSVERBAND
        // ...
        // [60] => CORE / MANDATSREF.:
        // [61] => 000000009999
        // [62] => GLÄUBIGER-ID:
        // [63] => DE9702100000099999
        // [66] => Ref. 9999999999


        // first line -> TYPE+
        // some lines 'SVWZ'
        // optional 'END-TO-END-REF.:' -> EREF
        // optional 'CORE / MANDATSREF.:' -> MREF
        // optional 'GLÄUBIGER-ID: ' -> CRED
        // optional 'Ref. ' -> BREF

        // rebuild description lines and extract other keys

        $svwz = [];   // SVWZ description lines
        $ret = [];   // data other (other keys)
        $rl = [];   // rawline compacted
        $eteseen = false;
        $eteref = '';
        // get available entries 20-29 and 60-69 into a consecutive array
        for ($i = 20; $i < 69; ++$i) {
            if ($i >= 30 && $i <= 59)
                continue;
            if (isset($rawLines[$i]))
                array_push($rl, $rawLines[$i]);
        }
        for ($i = 0; $i < count($rl); ++$i) {
            $line = $rl[$i];
            $nline = $i < count($rl) - 1 ? $rl[$i + 1] : '';
            switch ($line) {
                case 'END-TO-END-REF.:':
                    $ret['EREF'] = $nline;
                    ++$i;
                    break;
                case 'CORE / MANDATSREF.:':
                    $ret['MREF'] = $nline;
                    ++$i;
                    break;
                case 'GLÄUBIGER-ID:':
                    $ret['CRED'] = $nline;
                    ++$i;
                    break;
                default:
                    if (substr($line, 0, 5) == 'Ref. ') {
                        $ret['BREF'] = $line;
                    } else {
                        array_push($svwz, $line);
                    }
                    break;
            }
        }

        $bookingType = array_shift($svwz);
        $rawLines[0] = $bookingType;
        $ret['TYPE'] = $bookingType;
        $ret['SVWZ'] = implode('', $svwz);

        return $ret;
    }
}
