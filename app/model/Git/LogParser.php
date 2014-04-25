<?php

namespace Git;

/**
 * Description of LogParser
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class LogParser
{
	public static function parseLines(array $lines)
	{
		$commits = array_map(function($line){
			return self::parseLine($line);
		}, $lines);
		
		return $commits;
	}
	
	public static function parseLine($line)
	{
		$commit = [];
		parse_str($line, $commit);
		return $commit;
	}
}

