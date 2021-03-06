<?php
/** Latvian (Latviešu)
 *
 * See MessagesQqq.php for message documentation incl. usage of parameters
 * To improve a translation please visit http://translatewiki.net
 *
 * @ingroup Language
 * @file
 *
 * @author Dark Eagle
 * @author FnTmLV
 * @author Geimeris
 * @author GreenZeb
 * @author Kaganer
 * @author Karlis
 * @author Kikos
 * @author Knakts
 * @author Marozols
 * @author Papuass
 * @author Reedy
 * @author Xil
 * @author Yyy
 * @author לערי ריינהארט
 */

/**
 * @copyright Copyright © 2006, Niklas Laxström
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$namespaceNames = array(
	NS_MEDIA            => 'Media',
	NS_SPECIAL          => 'Special',
	NS_TALK             => 'Diskusija',
	NS_USER             => 'Lietotājs',
	NS_USER_TALK        => 'Lietotāja_diskusija',
	NS_PROJECT_TALK     => '{{grammar:ģenitīvs|$1}}_diskusija',
	NS_FILE             => 'Attēls',
	NS_FILE_TALK        => 'Attēla_diskusija',
	NS_MEDIAWIKI        => 'MediaWiki',
	NS_MEDIAWIKI_TALK   => 'MediaWiki_diskusija',
	NS_TEMPLATE         => 'Veidne',
	NS_TEMPLATE_TALK    => 'Veidnes_diskusija',
	NS_HELP             => 'Palīdzība',
	NS_HELP_TALK        => 'Palīdzības_diskusija',
	NS_CATEGORY         => 'Kategorija',
	NS_CATEGORY_TALK    => 'Kategorijas_diskusija',
);
$separatorTransformTable = array( ',' => "\xc2\xa0", '.' => ',' );

$pluralRules = [
	"n % 10 = 0 or n % 100 = 11..19 or v = 2 and f % 100 = 11..19",
	"n % 10 = 1 and n % 100 != 11 or v = 2 and f % 10 = 1 and f % 100 != 11 or v != 2 and f % 10 = 1",
];
