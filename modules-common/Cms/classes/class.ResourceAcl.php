<?php

class ResourceAcl
{
	public const string _ACL_VIEW = 'view';
	public const string _ACL_EDIT = 'edit';
	public const string _ACL_DELETE = 'delete';
	public const string _ACL_PUBLISH = 'publish';
	public const string _ACL_LIST = 'list';
	public const string _ACL_CREATE = 'create';

	public static function assignToUsergroup(int $usergroup_id, int $resource_id): bool
	{
		$savedata = [
			'subject_type' => 'usergroup',
			'subject_id' => $usergroup_id,
			'resource_id' => $resource_id,
		];

		return DbHelper::insertOrUpdateHelper('resource_acl', $savedata) > 0;
	}

	public static function removeFromUsergroup(int $usergroup_id, int $resource_id): bool
	{
		$data = DbHelper::selectMany('resource_acl', [
			'resource_id' => $resource_id,
			'subject_type' => 'usergroup',
			'subject_id' => $usergroup_id,
		]);

		if (!isset($data[0]['acl_id'])) {
			return false;
		}

		return DbHelper::deleteHelper('resource_acl', $data[0]['acl_id']);
	}

	public static function assignToUser($user_id, $resource_id): bool
	{
		$savedata = [
			'subject_type' => 'user',
			'subject_id' => $user_id,
			'resource_id' => $resource_id,
		];

		return DbHelper::insertOrUpdateHelper('resource_acl', $savedata) > 0;
	}

	public static function removeFromUser(int $user_id, int $resource_id): bool
	{
		$data = DbHelper::selectMany('resource_acl', [
			'resource_id' => $resource_id,
			'subject_type' => 'user',
			'subject_id' => $user_id,
		]);

		if (!isset($data[0]['acl_id'])) {
			return false;
		}

		return DbHelper::deleteHelper('resource_acl', $data[0]['acl_id']);
	}

	public static function checkUserIsAssigned(int $resource_id, int $user_id): bool
	{
		$data = DbHelper::selectMany('resource_acl', [
			'resource_id' => $resource_id,
			'subject_type' => 'user',
			'subject_id' => $user_id,
		]);

		return count($data) > 0;
	}

	public static function checkUsergroupIsAssigned(int $resource_id, int $usergroup_id): bool
	{
		$data = DbHelper::selectMany('resource_acl', [
			'resource_id' => $resource_id,
			'subject_type' => 'usergroup',
			'subject_id' => $usergroup_id,
		]);

		return count($data) > 0;
	}

	public static function setInheritance(int $resource_id, bool $inheritance): int
	{
		$savedata = ['is_inheriting_acl' => $inheritance, ];

		return DbHelper::updateHelper('resource_tree', $savedata, $resource_id);
	}

	public static function getInheritedResourcesList(int $resource_id, bool $include_self = true): array
	{
		if ($include_self) {
			$return = [$resource_id];
		} else {
			$return = [];
		}

		$resource_data = ResourceTreeHandler::getResourceTreeEntryDataById($resource_id);

		if (!is_array($resource_data)) {
			return [];
		}

		if (!$resource_data['is_inheriting_acl']) {
			return $return;
		}

		$query = "
            SELECT
            node_id,
            is_inheriting_acl
            FROM
            resource_tree
            WHERE lft < ?
            AND rgt > ?
            ORDER BY lft DESC;
        ";

		$stmt = Db::instance()->prepare($query);
		$stmt->execute([
			$resource_data['lft'],
			$resource_data['rgt'],
		]);

		$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rs as $resource) {
			$return[] = (int)$resource['node_id'];

			if ($resource['is_inheriting_acl'] != 1) {
				break;
			}
		}

		return $return;
	}

	public static function canAccessResource(int $resource_id, string $operation): bool
	{
		if (CLITrustPolicy::isTrustedOperatorCli()) {
			return true;
		}

		if (Roles::hasRole(RoleList::ROLE_SYSTEM_DEVELOPER) && Config::DEV_DEVELOPERS_CAN_ACCESS_ALL_RESOURCES->value() === true) {
			return true;
		}

		$operation = 'allow_' . $operation;

		// 1. összes felhasználói csoport kiválasztása
		$current_user_id = User::getCurrentUserId();
		$users_active_groups = Usergroups::getAllUsergroupsForUser($current_user_id);

		// 2. resource_id-k listája az öröklésekkel együtt
		$resource_list = self::getInheritedResourcesList($resource_id);

		if ($resource_list === []) {
			return false;
		}

		// 3. user és csoport hozzárendelések keresése az összes resource_id-re
		$query = "
            SELECT
            COUNT(1) > 0 AS has_access
            FROM
            resource_acl
            WHERE
            (
                resource_id IN (" . implode(',', $resource_list) . ")
                AND subject_type = 'user'
                AND subject_id = ?
                AND {$operation} = 1
            )
            OR
            (
                resource_id IN (" . implode(',', $resource_list) . ")
                AND subject_type = 'usergroup'
                AND subject_id IN (" . implode(',', $users_active_groups) . ")
                AND {$operation} = 1
            )
            LIMIT 1
        ";

		$stmt = Db::instance()->prepare($query);
		$stmt->execute([$current_user_id]);

		$has_access = $stmt->fetch(PDO::FETCH_COLUMN);

		return $has_access == true;
	}

	/**
	 * Retrieves a list of unique subjects (users or roles) that have access to a given resource
	 * by considering the ACL entries of resources from which the given resource inherits permissions.
	 *
	 * @param int $resource_id The ID of the resource for which to retrieve the subjects.
	 * @return array An array of unique subjects that have access to the given resource.
	 */
	public static function getSubjectsListOfAncestorAclObjects(int $resource_id): array
	{
		$resource_ids = self::getInheritedResourcesList($resource_id, false);

		if (count($resource_ids) < 1) {
			return [];
		}

		$query = "
            SELECT
              *
            FROM
              resource_acl
            WHERE resource_id IN (" . implode(',', $resource_ids) . ") ;
        ";

		$subjects = DbHelper::selectManyFromQuery($query);

		$unique_subjects = [];

		foreach ($subjects as $subject) {
			$unique_subjects[$subject['subject_type'] . $subject['subject_id']] = $subject;
		}

		return $unique_subjects;
	}

	public static function getSubjectsListOfAclObject(int $resource_id): array
	{
		$query = "
            SELECT
              *
            FROM
              resource_acl
            WHERE resource_id=?;
        ";

		$subjects = DbHelper::selectManyFromQuery(
			$query,
			[$resource_id]
		);

		$unique_subjects = [];

		foreach ($subjects as $subject) {
			$unique_subjects[$subject['subject_type'] . $subject['subject_id']] = $subject;
		}

		return $unique_subjects;
	}

	public static function getAclValues(int $acl_id): ?array
	{
		return DbHelper::selectOne('resource_acl', ['acl_id' => $acl_id]);
	}

	public static function updateAcl(int $acl_id, array $savedata): int
	{
		return DbHelper::updateHelper('resource_acl', $savedata, $acl_id);
	}

	public static function deleteAcl(int $acl_id): bool
	{
		return DbHelper::deleteHelper('resource_acl', $acl_id);
	}

	public static function getObjectListForSelect(string $term): array
	{
		$query = "
            SELECT
                'user' AS object_type, username AS object_name
            FROM
                users
            WHERE username LIKE ?
            UNION
            SELECT
                'usergroup' AS object_type, title AS objectname
            FROM
                usergroups_tree
            WHERE title LIKE ?
            ORDER BY object_name;
        ";

		return DbHelper::selectManyFromQuery(
			$query,
			["%{$term}%", "%{$term}%"]
		);
	}
}
