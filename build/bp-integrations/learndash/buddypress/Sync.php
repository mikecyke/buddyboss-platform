<?php
/**
 * BuddyBoss LearnDash integration sync class.
 *
 * @package BuddyBoss\LearnDash
 * @since BuddyBoss 1.0.0
 */

namespace Buddyboss\LearndashIntegration\Buddypress;

use Buddyboss\LearndashIntegration\Library\SyncGenerator;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for all syncing related functions
 *
 * @since BuddyBoss 1.0.0
 */
class Sync
{
	// temporarily hold the synced learndash group id just before delete
	protected $deletingSyncedLdGroupId;

	/**
	 * Constructor
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function __construct()
	{
		add_action('bp_ld_sync/init', [$this, 'init']);
	}

	/**
	 * Add actions once integration is ready
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function init()
	{
		add_action('bp_ld_sync/buddypress_group_created', [$this, 'onGroupCreate']);
		add_action('bp_ld_sync/buddypress_group_updated', [$this, 'onGroupUpdate']);
		add_action('bp_ld_sync/buddypress_group_deleting', [$this, 'onGroupDeleting']);
		add_action('bp_ld_sync/buddypress_group_deleted', [$this, 'onGroupDeleted']);

		add_action('bp_ld_sync/buddypress_group_admin_added', [$this, 'onAdminAdded'], 10, 3);
		add_action('bp_ld_sync/buddypress_group_mod_added', [$this, 'onModAdded'], 10, 3);
		add_action('bp_ld_sync/buddypress_group_member_added', [$this, 'onMemberAdded'], 10, 3);

		add_action('bp_ld_sync/buddypress_group_admin_removed', [$this, 'onAdminRemoved'], 10, 3);
		add_action('bp_ld_sync/buddypress_group_mod_removed', [$this, 'onModRemoved'], 10, 3);
		add_action('bp_ld_sync/buddypress_group_member_removed', [$this, 'onMemberRemoved'], 10, 3);
		// add_action('bp_ld_sync/buddypress_group_member_banned', [$this, 'onMemberRemoved'], 10, 3);
	}

	/**
	 * Get Sync generator object
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function generator($bpGroupId = null, $ldGroupId = null)
	{
		return new SyncGenerator($bpGroupId, $ldGroupId);
	}

	/**
	 * Run the sync when new group is created
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onGroupCreate($groupId)
	{
		if (! $this->preCheck()) {
			return;
		}

		$settings = bp_ld_sync('settings');

		// on the group creation first step, and create tab is enabled, we create sync group in later step
		if ('group-details' == bp_get_groups_current_create_step() && $settings->get('buddypress.show_in_bp_create')) {
			return false;
		}

		// if auto sync is turn off
		if (! $settings->get('buddypress.default_auto_sync')) {
			return false;
		}

		// admin is added BEFORE this hook is called, so we need to manually sync admin
		// src/bp-groups/bp-groups-functions.php:194
		$this->generator($groupId)->associateToLearndash()->syncBpAdmins();
	}

	/**
	 * Run the sync when new group is updated
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onGroupUpdate($groupId)
	{
		if (! $this->preCheck()) {
			return;
		}

		$this->generator($groupId)->fullSyncToLearndash();
	}

	/**
	 * Set the deleted gropu in temporarly variable for later use
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onGroupDeleting($groupId)
	{
		if (! $this->preCheck()) {
			return;
		}

		$this->deletingSyncedLdGroupId = $this->generator($groupId)->getLdGroupId();
	}

	/**
	 * Desync when group is deleted
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onGroupDeleted($groupId)
	{
		if (! $this->enabled()) {
			return false;
		}

		if (! $ldGroupId = $this->deletingSyncedLdGroupId) {
			return;
		}

		$this->deletingSyncedLdGroupId = null;

		if (! bp_ld_sync('settings')->get('buddypress.delete_ld_on_delete')) {
			$this->generator(null, $ldGroupId)->desyncFromBuddypress();
			return;
		}

		$this->generator()->deleteLdGroup($ldGroupId);
	}

	/**
	 * Sync when a admin is added to the group
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onAdminAdded($groupId, $memberId, $groupMemberObject)
	{
		if (! $generator = $this->groupUserEditCheck('admin', $groupId)) {
			return false;
		}

		$generator->syncBpAdmin($memberId);
	}

	/**
	 * Sync when a mod is added to the group
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onModAdded($groupId, $memberId, $groupMemberObject)
	{
		if (! $generator = $this->groupUserEditCheck('mod', $groupId)) {
			return false;
		}

		$generator->syncBpMod($memberId);
	}

	/**
	 * Sync when a member is added to the group
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onMemberAdded($groupId, $memberId, $groupMemberObject)
	{
		if (! $generator = $this->groupUserEditCheck('user', $groupId)) {
			return false;
		}

		$generator->syncBpMember($memberId);
	}

	/**
	 * Sync when a admin is removed from the group
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onAdminRemoved($groupId, $memberId, $groupMemberObject)
	{
		if (! $generator = $this->groupUserEditCheck('admin', $groupId)) {
			return false;
		}

		$generator->syncBpAdmin($memberId, true);
	}

	/**
	 * Sync when a mod is removed from the group
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function onModRemoved($groupId, $memberId, $groupMemberObject)
	{
		if (! $generator = $this->groupUserEditCheck('mod', $groupId)) {
			return false;
		}

		$generator->syncBpMod($memberId, true);
	}

	/**
	 * Sync when a members is removed from the group
	 *
	 * @since BuddyBoss 1.0.0
	 */
	//public function onMemberRemoved($groupId, $memberId, $groupMemberObject)
	// {
	// 	if (! $generator = $this->groupUserEditCheck('user', $groupId)) {
	// 		return false;
	// 	}

	// 	$generator->syncBpMember($memberId, true);
	// }

	/**
	 * Check if the user type need to be synced
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function groupUserEditCheck($role, $groupId)
	{
		if (! $this->preCheck()) {
			return;
		}

		$settings = bp_ld_sync('settings');

		if (! $settings->get('buddypress.enabled')) {
			return false;
		}

		if ('none' == $settings->get("buddypress.default_{$role}_sync_to")) {
			return false;
		}

		$generator = $this->generator($groupId);

		if (! $generator->hasLdGroup()) {
			return false;
		}

		return $generator;
	}

	/**
	 * Standard pre check bore all sync happens
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function preCheck()
	{
		global $bp_ld_sync__syncing_to_buddypress;

		// if it's group is created from buddypress sync, don't need to sync back
		if ($bp_ld_sync__syncing_to_buddypress) {
			return false;
		}

		return $this->enabled();
	}

	/**
	 * Returns if buddypress sync is enabled or not
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function enabled()
	{
		return bp_ld_sync('settings')->get('buddypress.enabled');
	}
}