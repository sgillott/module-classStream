<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// This file describes the module, including database tables

// Basic variables
$name        = 'Class Stream';
$description = 'A Google Classroom-style stream for every class: announcements with links and files, class comments, and the homework from the Planner, all in one place.';
$entryURL    = 'stream.php';
$type        = 'Additional';
$category    = 'Learn';
$version     = '0.8.00';
$author      = 'Steve Gillott';
$url         = '';

// Module tables
// Six module-owned tables. Comments are not stored here: they live in core's general-purpose
// gibbonDiscussion table (foreignTable='classStreamPost', gibbonModuleID=this module), read and
// written through Gibbon\Domain\System\DiscussionGateway's table. No core table is altered.

// One row per post. gibbonSchoolYearID is denormalised from the class so the Reuse page can list a
// past year's posts without joining through gibbonCourse each time. classStreamPostIDSource records
// where a synced or reused post was copied from. timestampPublished is when the post is (or will
// be) on the stream: NULL is a draft, a future time is scheduled. notified flips to Y once the
// class has been told about it, which happens on the first stream view after it is due.
$moduleTables[] = "CREATE TABLE `classStreamPost` (
    `classStreamPostID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `gibbonSchoolYearID` int(3) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `type` enum('Announcement','Material') NOT NULL DEFAULT 'Announcement',
    `title` varchar(120) DEFAULT NULL,
    `body` text,
    `pinned` enum('N','Y') NOT NULL DEFAULT 'N',
    `classStreamPostIDSource` int(12) UNSIGNED ZEROFILL DEFAULT NULL,
    `timestamp` timestamp NULL DEFAULT NULL,
    `timestampModified` timestamp NULL DEFAULT NULL,
    `timestampPublished` datetime DEFAULT NULL,
    `notified` enum('N','Y') NOT NULL DEFAULT 'N',
    PRIMARY KEY (`classStreamPostID`),
    KEY `gibbonCourseClassID` (`gibbonCourseClassID`,`timestampPublished`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;";

// Many attachments per post. A File location is the relative uploads path returned by core's
// FileUploader; a Link location is the URL as typed.
$moduleTables[] = "CREATE TABLE `classStreamPostAttachment` (
    `classStreamPostAttachmentID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `classStreamPostID` int(12) UNSIGNED ZEROFILL NOT NULL,
    `type` enum('File','Link') NOT NULL DEFAULT 'Link',
    `name` varchar(255) NOT NULL DEFAULT '',
    `location` text NOT NULL,
    `sequenceNumber` int(3) NOT NULL DEFAULT 0,
    PRIMARY KEY (`classStreamPostAttachmentID`),
    KEY `classStreamPostID` (`classStreamPostID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;";

// Per-class appearance and behaviour, set by the class teacher on the Customise page. A class with
// no row uses the module defaults. studentAccess NULL means "use the module setting".
$moduleTables[] = "CREATE TABLE `classStreamClass` (
    `classStreamClassID` int(8) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `colour` varchar(20) DEFAULT NULL,
    `headerImage` varchar(255) DEFAULT NULL,
    `studentAccess` enum('Post','Comment','None') DEFAULT NULL,
    `plannerDisplay` enum('Details','Condensed','Hidden') NOT NULL DEFAULT 'Condensed',
    PRIMARY KEY (`classStreamClassID`),
    UNIQUE KEY `gibbonCourseClassID` (`gibbonCourseClassID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;";

// A muted student can read the stream of that class but cannot post or comment in it.
$moduleTables[] = "CREATE TABLE `classStreamMute` (
    `classStreamMuteID` int(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDMutedBy` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestamp` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`classStreamMuteID`),
    UNIQUE KEY `gibbonCourseClassID` (`gibbonCourseClassID`,`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;";

// One row per person per class: when they last opened that stream, and how many times. Drives
// the "N new" counts on the cards and the Insights page. Written on every stream view, never read
// for access.
$moduleTables[] = "CREATE TABLE `classStreamView` (
    `classStreamViewID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `timestamp` timestamp NULL DEFAULT NULL,
    `visitCount` int(8) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`classStreamViewID`),
    UNIQUE KEY `gibbonPersonID` (`gibbonPersonID`,`gibbonCourseClassID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;";

// One-way links between classes: an announcement or material posted in the source class is copied
// into the target class (never comments). "Sync" on the Customise page is two rows, one each way.
$moduleTables[] = "CREATE TABLE `classStreamLink` (
    `classStreamLinkID` int(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `gibbonCourseClassIDTarget` int(8) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDCreated` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestamp` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`classStreamLinkID`),
    UNIQUE KEY `link` (`gibbonCourseClassID`,`gibbonCourseClassIDTarget`),
    KEY `gibbonCourseClassIDTarget` (`gibbonCourseClassIDTarget`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;";

// Settings
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Class Stream', 'defaultStudentAccess', 'Default Student Access', 'What students may do in a class stream, unless the class teacher changes it for their class.', 'Comment');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Class Stream', 'showHomework', 'Show Homework', 'Show homework set in the Planner on the class stream.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Class Stream', 'showLessons', 'Show Lessons', 'Allow planned lessons from the Planner to appear on the class stream, not only homework.', 'N');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Class Stream', 'parentView', 'Parent View', 'What parents can see besides teacher posts: only their own child\'s posts and comments, every post and comment with other students\' names and photos hidden, or no student posts or comments at all.', 'Own child');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Class Stream', 'notifyOnPost', 'Notify On Post', 'Send a Gibbon notification to class members when a new post is made.', 'Y');";

// Action rows
// Access is a grouped set (core's Markbook/Planner convention), highest precedence wins when a
// role holds several: _myClasses (0), _myChildrensClasses (0), _departmentClassesView (1),
// _allClasses (2). isActionAccessible() only decides whether a role may enter these pages at all;
// what a person may do inside one class (post, comment, customise, mute) is decided per class by
// src/StreamAccess.php from their gibbonCourseClassPerson role and the class settings. There is
// no separate "post" action: a teacher of a class can always post in it, and student posting is a
// per-class setting, not a role permission. Parents hold only _myChildrensClasses, which reaches
// the two read-only pages; do not grant a role both _myClasses and _myChildrensClasses.
// _departmentClassesView is for a Head of Department: their own classes as a teacher, plus read
// and comment in every class of the departments they are staff of (gibbonDepartmentStaff).
$actionRows[] = [
    'name'                      => 'View Class Streams_myClasses',
    'precedence'                => '0',
    'category'                  => 'Class Stream',
    'description'               => 'View the streams of the classes the user belongs to.',
    'URLList'                   => 'stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php,stream_post_delete.php,stream_post_publish.php',
    'entryURL'                  => 'stream.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'Y',
];
$actionRows[] = [
    'name'                      => 'View Class Streams_allClasses',
    'precedence'                => '2',
    'category'                  => 'Class Stream',
    'description'               => 'View and moderate the stream of any class.',
    'URLList'                   => 'stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php,stream_post_delete.php,stream_post_publish.php',
    'entryURL'                  => 'stream.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];
$actionRows[] = [
    'name'                      => 'View Class Streams_departmentClassesView',
    'precedence'                => '1',
    'category'                  => 'Class Stream',
    'description'               => 'View and comment in the streams of every class in the departments the user is staff of, on top of their own classes. Cannot post, customise or moderate there.',
    'URLList'                   => 'stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php,stream_post_delete.php,stream_post_publish.php',
    'entryURL'                  => 'stream.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];
$actionRows[] = [
    'name'                      => 'View Class Streams_myChildrensClasses',
    'precedence'                => '0',
    'category'                  => 'Class Stream',
    'description'               => 'View, read only, the streams of the classes the user\'s children belong to, through the lens the Parent View setting chooses.',
    'URLList'                   => 'stream.php,stream_view.php',
    'entryURL'                  => 'stream.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'N',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];
$actionRows[] = [
    'name'                      => 'Class Stream Settings',
    'precedence'                => '0',
    'category'                  => 'Settings',
    'description'               => 'Set the defaults for student access, Planner items, parent view and notifications.',
    'URLList'                   => 'settings.php,settingsProcess.php',
    'entryURL'                  => 'settings.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// Hooks
// One "Class Stream" tab on each dashboard, all served by hook_dashboard.php. sourceModuleAction
// is the comma list core's HookGateway matches with FIND_IN_SET: the tab shows for any role that
// holds one of those actions. Core creates hook rows on install only; CHANGEDB.php adds them for
// an existing install.
$hookOptions = function ($actions) {
    return serialize(['sourceModuleName' => 'Class Stream', 'sourceModuleAction' => $actions, 'sourceModuleInclude' => 'hook_dashboard.php']);
};
$hooks[] = "INSERT INTO gibbonHook SET name='Class Stream', type='Staff Dashboard', options='".$hookOptions('View Class Streams_myClasses,View Class Streams_allClasses,View Class Streams_departmentClassesView')."', gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream');";
$hooks[] = "INSERT INTO gibbonHook SET name='Class Stream', type='Student Dashboard', options='".$hookOptions('View Class Streams_myClasses')."', gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream');";
$hooks[] = "INSERT INTO gibbonHook SET name='Class Stream', type='Parental Dashboard', options='".$hookOptions('View Class Streams_myChildrensClasses')."', gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream');";
