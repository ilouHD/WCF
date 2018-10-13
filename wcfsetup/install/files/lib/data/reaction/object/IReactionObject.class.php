<?php
namespace wcf\data\reaction\object;
use wcf\data\like\Like;

/**
 * Any reactionable object, which supports notifications, should implement this interface.
 * 
 * @TODO backport this for forward compatibility
 * 
 * @author	Joshua Ruesweg
 * @copyright	2001-2018 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Data\Reaction\Object
 * @since       3.2
 */
interface IReactionObject {
	/**
	 * Sends a notification for this reaction.
	 *
	 * @param	Like	$like
	 */
	public function sendNotification(Like $like);
}