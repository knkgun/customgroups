<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\CustomGroups\Tests\unit\Dav;

use OCA\CustomGroups\Dav\GroupsCollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\Exception\ValidationException;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IUser;
use OCA\CustomGroups\Dav\GroupMembershipCollection;
use OCA\CustomGroups\Service\MembershipHelper;
use OCP\IGroupManager;
use OCA\CustomGroups\Search;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\IConfig;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Sabre\DAV\MkCol;

/**
 * Class GroupsCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class GroupsCollectionTest extends \Test\TestCase {
	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var GroupsCollection
	 */
	private $collection;

	/**
	 * @var MembershipHelper
	 */
	private $helper;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var IConfig
	 */
	private $config;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->handler->expects($this->never())->method('getGroup');
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')
			->with()
			->will($this->returnValueMap([
				['customgroups', 'allow_duplicate_names', 'false', false],
				['customgroups', 'only_subadmin_can_create', 'false', false],
			]));

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager,
			$this->createMock(IManager::class),
			$this->createMock(IURLGenerator::class),
			$this->config
		);
		$this->collection = new GroupsCollection(
			$this->createMock(IGroupManager::class),
			$this->handler,
			$this->helper,
			$this->config
		);
	}

	public function testBase() {
		$this->assertEquals('groups', $this->collection->getName());
		$this->assertNull($this->collection->getLastModified());
	}

	public function testListGroups() {
		$this->handler->expects($this->once())
			->method('getGroups')
			->with(null)
			->will($this->returnValue([
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
				['group_id' => 2, 'uri' => 'group2', 'display_name' => 'Group Two'],
			]));

		$nodes = $this->collection->getChildren();
		$this->assertCount(2, $nodes);

		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[0]);
		$this->assertEquals('group1', $nodes[0]->getName());
		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[1]);
		$this->assertEquals('group2', $nodes[1]->getName());
	}

	public function testListGroupsSearchPattern() {
		$search = new Search('gr', 16, 256);
		$this->handler->expects($this->once())
			->method('getGroups')
			->with($search)
			->will($this->returnValue([
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
				['group_id' => 2, 'uri' => 'group2', 'display_name' => 'Group Two'],
			]));

		$nodes = $this->collection->search($search);
		$this->assertCount(2, $nodes);

		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[0]);
		$this->assertEquals('group1', $nodes[0]->getName());
		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[1]);
		$this->assertEquals('group2', $nodes[1]->getName());
	}

	public function testListGroupsForUser() {
		$collection = new GroupsCollection(
			$this->createMock(IGroupManager::class),
			$this->handler,
			$this->helper,
			$this->config,
			'user1'
		);
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->handler->expects($this->never())->method('getGroups');
		$this->handler->expects($this->once())
			->method('getUserMemberships')
			->with('user1', null)
			->will($this->returnValue([
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
				['group_id' => 2, 'uri' => 'group2', 'display_name' => 'Group Two'],
			]));

		$nodes = $collection->getChildren();
		$this->assertCount(2, $nodes);

		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[0]);
		$this->assertEquals('group1', $nodes[0]->getName());
		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[1]);
		$this->assertEquals('group2', $nodes[1]->getName());
	}

	public function testListGroupsForUserSearchPattern() {
		$search = new Search('gr', 16, 256);

		$collection = new GroupsCollection(
			$this->createMock(IGroupManager::class),
			$this->handler,
			$this->helper,
			$this->config,
			'user1'
		);
		$this->handler->expects($this->never())->method('getGroups');
		$this->handler->expects($this->once())
			->method('getUserMemberships')
			->with('user1', $search)
			->will($this->returnValue([
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
				['group_id' => 2, 'uri' => 'group2', 'display_name' => 'Group Two'],
			]));

		$nodes = $collection->search($search);
		$this->assertCount(2, $nodes);

		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[0]);
		$this->assertEquals('group1', $nodes[0]->getName());
		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[1]);
		$this->assertEquals('group2', $nodes[1]->getName());
	}

	public function testCreateGroup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$this->handler->expects($this->once())
			->method('getGroupsByDisplayName')
			->with('Group One')
			->will($this->returnValue([]));
		$this->handler->expects($this->once())
			->method('createGroup')
			->with('group1', 'Group One')
			->will($this->returnValue(1));
		$this->handler->expects($this->once())
			->method('addToGroup')
			->with('user1', 1, true);

		$called = [];
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::addGroupAndUser', function ($event) use (&$called) {
			$called[] = '\OCA\CustomGroups::addGroupAndUser';
			\array_push($called, $event);
		});

		$mkCol = new MkCol([], [
			GroupMembershipCollection::PROPERTY_DISPLAY_NAME => 'Group One'
		]);
		$this->collection->createExtendedCollection('group1', $mkCol);

		$this->assertSame('\OCA\CustomGroups::addGroupAndUser', $called[0]);
		$this->assertTrue($called[1] instanceof GenericEvent);
		$this->assertArrayHasKey('groupName', $called[1]);
		$this->assertArrayHasKey('user', $called[1]);
		$this->assertArrayHasKey('groupId', $called[1]);
		$this->assertEquals('group1', $called[1]->getArgument('groupName'));
		$this->assertEquals('user1', $called[1]->getArgument('user'));
		$this->assertEquals(1, $called[1]->getArgument('groupId'));

		$this->assertEquals(202, $mkCol->getResult()[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
	}

	/**
	 */
	public function testCreateGroupNoDuplicates() {
		$this->expectException(\Sabre\DAV\Exception\Conflict::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$this->handler->expects($this->once())
			->method('getGroupsByDisplayName')
			->with('Group One')
			->will($this->returnValue([['duplicate']]));

		$called = [];
		\OC::$server->getEventDispatcher()->addListener('addGroupAndUser', function ($event) use (&$called) {
			$called[] = 'addGroupAndUser';
			\array_push($called, $event);
		});

		$mkCol = new MkCol([], [
			GroupMembershipCollection::PROPERTY_DISPLAY_NAME => 'Group One'
		]);
		$this->collection->createExtendedCollection('group1', $mkCol);
	}

	public function providesTestCreateException() {
		return [
			['', 'empty'],
			[null, 'empty'],
			[' abc', 'starts with space'],
			['a', 'only one char'],
			['á', 'one char multibyte'],
			['.', 'single dot'],
			['12345678911234567892123456789312345678941234567895123456789612345', 'name longer than 64 characters']
		];
	}

	/**
	 * @dataProvider providesTestCreateException
	 */
	public function testCreateGroupExceptions($groupName, $displayName) {
		$this->expectException(\OCA\CustomGroups\Exception\ValidationException::class);

		$mkCol = new MkCol([], [
			GroupMembershipCollection::PROPERTY_DISPLAY_NAME => $displayName
		]);
		$this->collection->createExtendedCollection($groupName, $mkCol);
	}

	/**
	 * Test the status code.
	 * @dataProvider providesTestCreateException
	 * @throws ValidationException
	 */
	public function testCreateGroupExceptionsStatusCode($groupName, $displayName) {
		$mkCol = new MkCol([], [
			GroupMembershipCollection::PROPERTY_DISPLAY_NAME => $displayName
		]);

		try {
			$this->collection->createExtendedCollection($groupName, $mkCol);
		} catch (ValidationException $exception) {
			$this->assertEquals(422, $exception->getHTTPCode());
		}
	}

	/**
	 */
	public function testCreateGroupNoPermission() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$helper = $this->createMock(MembershipHelper::class);
		$helper->expects($this->once())
			->method('canCreateGroups')
			->willReturn(false);

		$this->collection = new GroupsCollection(
			$this->createMock(IGroupManager::class),
			$this->handler,
			$helper,
			$this->config
		);

		$this->handler->expects($this->never())
			->method('createGroup');
		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->collection->createDirectory('group1');
	}

	/**
	 */
	public function testCreateGroupAlreadyExists() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->handler->expects($this->once())
			->method('createGroup')
			->with('group1', 'group1')
			->will($this->returnValue(null));
		$this->handler->expects($this->never())
			->method('addToGroup')
			->with('user1', 1, true);

		$this->collection->createDirectory('group1');
	}

	public function testGetGroup() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->with('group1')
			->will($this->returnValue(['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']));

		$groupNode = $this->collection->getChild('group1');
		$this->assertInstanceOf(GroupMembershipCollection::class, $groupNode);
		$this->assertEquals('group1', $groupNode->getName());
	}

	/**
	 */
	public function testGetGroupNonExisting() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);

		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->with('groupx')
			->will($this->returnValue(null));

		$this->collection->getChild('groupx');
	}

	public function testGroupExists() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->will($this->returnValueMap([
				['group1', ['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']],
				['group2', null],
			]));

		$this->assertTrue($this->collection->childExists('group1'));
		$this->assertFalse($this->collection->childExists('group2'));
	}

	/**
	 */
	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->setName('x');
	}

	/**
	 */
	public function testDelete() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->delete();
	}

	/**
	 */
	public function testCreateFile() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->createFile('somefile.txt');
	}
}
