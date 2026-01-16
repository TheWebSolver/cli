<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

enum TableRow: string {
	case Fetch  = 'No. of items Fetched';
	case Keys   = 'Collection Keys';
	case Index  = 'Indexed by Value of';
	case Accent = 'Accented Characters';
	case Byte   = 'Total Bytes Written';
	case Path   = 'Cache Filepath';
}
