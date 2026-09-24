<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v0.1.00
$sql[$count][0] = "0.1.00";
$sql[$count][1] = "-- First installable version. All four tables and the settings are created by manifest.php on install; nothing to migrate.";

// v0.2.00
$count++;
$sql[$count][0] = "0.2.00";
$sql[$count][1] = "INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther)
SELECT gibbonModuleID, 'View Class Streams_myChildrensClasses', 0, 'Class Stream', 'View, read only, the streams of the classes the user\'s children belong to, through the lens the Parent View setting chooses.', 'stream.php,stream_view.php', 'stream.php', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'Y', 'N'
FROM gibbonModule WHERE name='Class Stream' AND NOT EXISTS (SELECT 1 FROM gibbonAction WHERE name='View Class Streams_myChildrensClasses' AND gibbonModuleID=gibbonModule.gibbonModuleID)
;end
INSERT INTO gibbonPermission (permissionID, gibbonRoleID, gibbonActionID)
SELECT NULL, '004', gibbonActionID FROM gibbonAction WHERE name='View Class Streams_myChildrensClasses' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
AND NOT EXISTS (SELECT 1 FROM gibbonPermission WHERE gibbonRoleID='004' AND gibbonPermission.gibbonActionID=gibbonAction.gibbonActionID)
;end
-- Parents arrive in this version through their own grouped action, granted to the Parent role
-- (004) as a fresh install would. Core's module updater runs only this file, never the manifest's
-- action rows, so an existing install gets the action here. No table changes: Planner rows are
-- read live from gibbonPlannerEntry and comments on them reuse gibbonDiscussion.";

// v0.3.00
$count++;
$sql[$count][0] = "0.3.00";
$hookOptions = function ($actions) {
    return serialize(['sourceModuleName' => 'Class Stream', 'sourceModuleAction' => $actions, 'sourceModuleInclude' => 'hook_dashboard.php']);
};
$sql[$count][1] = "CREATE TABLE IF NOT EXISTS `classStreamView` (
    `classStreamViewID` int(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `timestamp` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`classStreamViewID`),
    UNIQUE KEY `gibbonPersonID` (`gibbonPersonID`,`gibbonCourseClassID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3
;end
UPDATE gibbonAction SET precedence=2 WHERE name='View Class Streams_allClasses' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
;end
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther)
SELECT gibbonModuleID, 'View Class Streams_departmentClassesView', 1, 'Class Stream', 'View and comment in the streams of every class in the departments the user is staff of, on top of their own classes. Cannot post, customise or moderate there.', 'stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php', 'stream.php', 'Y', 'Y', 'N', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N'
FROM gibbonModule WHERE name='Class Stream' AND NOT EXISTS (SELECT 1 FROM gibbonAction WHERE name='View Class Streams_departmentClassesView' AND gibbonModuleID=gibbonModule.gibbonModuleID)
;end
INSERT INTO gibbonHook (name, type, options, gibbonModuleID)
SELECT 'Class Stream', 'Staff Dashboard', '".$hookOptions('View Class Streams_myClasses,View Class Streams_allClasses,View Class Streams_departmentClassesView')."', gibbonModuleID FROM gibbonModule WHERE name='Class Stream' AND NOT EXISTS (SELECT 1 FROM gibbonHook WHERE type='Staff Dashboard' AND gibbonModuleID=gibbonModule.gibbonModuleID)
;end
INSERT INTO gibbonHook (name, type, options, gibbonModuleID)
SELECT 'Class Stream', 'Student Dashboard', '".$hookOptions('View Class Streams_myClasses')."', gibbonModuleID FROM gibbonModule WHERE name='Class Stream' AND NOT EXISTS (SELECT 1 FROM gibbonHook WHERE type='Student Dashboard' AND gibbonModuleID=gibbonModule.gibbonModuleID)
;end
INSERT INTO gibbonHook (name, type, options, gibbonModuleID)
SELECT 'Class Stream', 'Parental Dashboard', '".$hookOptions('View Class Streams_myChildrensClasses')."', gibbonModuleID FROM gibbonModule WHERE name='Class Stream' AND NOT EXISTS (SELECT 1 FROM gibbonHook WHERE type='Parental Dashboard' AND gibbonModuleID=gibbonModule.gibbonModuleID)
;end
-- New: classStreamView (last visit per person per class, for the new-post counts), the
-- Head of Department action (_departmentClassesView, precedence 1; _allClasses moves to 2 so it
-- still wins when a role holds both), and the three dashboard hook rows. Core's updater never
-- re-reads the manifest's actions or hooks, so an existing install gets them here, each guarded
-- so re-running is safe.";

// v0.4.00
$count++;
$sql[$count][0] = "0.4.00";
$sql[$count][1] = "CREATE TABLE IF NOT EXISTS `classStreamLink` (
    `classStreamLinkID` int(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonCourseClassID` int(8) UNSIGNED ZEROFILL NOT NULL,
    `gibbonCourseClassIDTarget` int(8) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDCreated` int(10) UNSIGNED ZEROFILL NOT NULL,
    `timestamp` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`classStreamLinkID`),
    UNIQUE KEY `link` (`gibbonCourseClassID`,`gibbonCourseClassIDTarget`),
    KEY `gibbonCourseClassIDTarget` (`gibbonCourseClassIDTarget`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3
;end
UPDATE gibbonAction SET URLList='stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php' WHERE name IN ('View Class Streams_myClasses', 'View Class Streams_allClasses', 'View Class Streams_departmentClassesView') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
;end
-- New: classStreamLink (mirror / sync between classes) and the two Reuse pages on the three
-- staff-side actions. No data changes.";

// v0.5.00
$count++;
$sql[$count][0] = "0.5.00";
$sql[$count][1] = "ALTER TABLE classStreamPost
    ADD COLUMN timestampPublished datetime DEFAULT NULL AFTER timestampModified,
    ADD COLUMN notified enum('N','Y') NOT NULL DEFAULT 'N' AFTER timestampPublished,
    DROP KEY gibbonCourseClassID,
    ADD KEY gibbonCourseClassID (gibbonCourseClassID, timestampPublished)
;end
UPDATE classStreamPost SET timestampPublished=timestamp, notified='Y' WHERE timestampPublished IS NULL
;end
UPDATE gibbonAction SET URLList='stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php' WHERE name IN ('View Class Streams_myClasses', 'View Class Streams_allClasses', 'View Class Streams_departmentClassesView') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
;end
-- Drafts and scheduled posts: timestampPublished says when a post is on the stream (NULL = draft,
-- future = scheduled). Every existing post was published the moment it was made and its class was
-- told then, so it gets its creation time and notified=Y. The Drafts page and the Publish Now
-- process join the three staff-side actions.";

// v0.6.00
$count++;
$sql[$count][0] = "0.6.00";
$sql[$count][1] = "UPDATE gibbonAction SET URLList='stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php,stream_post_delete.php,stream_post_publish.php' WHERE name IN ('View Class Streams_myClasses', 'View Class Streams_allClasses', 'View Class Streams_departmentClassesView') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
;end
-- The Drafts page now uses a DataTable with Gibbon's action buttons; Publish Now and Delete open
-- confirmation pages in a modal, which are registered here. No table changes. The colour palette
-- grew from 8 to 16 entries; stored colour keys are unchanged.";

// v0.7.00
$count++;
$sql[$count][0] = "0.7.00";
$sql[$count][1] = "ALTER TABLE classStreamView ADD COLUMN visitCount int(8) UNSIGNED NOT NULL DEFAULT 0 AFTER timestamp
;end
UPDATE classStreamView SET visitCount=1 WHERE visitCount=0
;end
UPDATE gibbonAction SET URLList='stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php,stream_post_delete.php,stream_post_publish.php,stream_insights.php' WHERE name IN ('View Class Streams_myClasses', 'View Class Streams_allClasses', 'View Class Streams_departmentClassesView') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
;end
-- Insights: classStreamView now counts visits (every existing row stands for at least one) and
-- the Insights page joins the three staff-side actions. Parent fixes need no data change.";

// v0.8.00
$count++;
$sql[$count][0] = "0.8.00";
$sql[$count][1] = "UPDATE gibbonAction SET URLList='stream.php,stream_view.php,stream_post_add.php,stream_post_addProcess.php,stream_post_edit.php,stream_post_editProcess.php,stream_post_deleteProcess.php,stream_post_attachment_deleteProcess.php,stream_commentProcess.php,stream_comment_deleteProcess.php,stream_customise.php,stream_customiseProcess.php,stream_people.php,stream_people_muteProcess.php,stream_reuse.php,stream_reuseProcess.php,stream_drafts.php,stream_post_publishProcess.php,stream_post_delete.php,stream_post_publish.php' WHERE name IN ('View Class Streams_myClasses', 'View Class Streams_allClasses', 'View Class Streams_departmentClassesView') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Class Stream')
;end
-- Insights folded into the People page; the separate page is gone from the URL lists.";

// v0.8.01
$count++;
$sql[$count][0] = "0.8.01";
$sql[$count][1] = "-- Code only: the module no longer calls two core methods that Gibbon v30 does not have
-- (CourseGateway::getCourseClassInfoByID and CourseClassPersonGateway), so it runs on v30 and v31.";

// v0.9.00
$count++;
$sql[$count][0] = "0.9.00";
$sql[$count][1] = "ALTER TABLE classStreamClass
    ADD COLUMN showAssessments enum('N','Y') NOT NULL DEFAULT 'Y' AFTER plannerDisplay,
    ADD COLUMN assessmentTypes text DEFAULT NULL AFTER showAssessments
;end
-- Assessment rows are read live from markbook columns. Existing classes receive the enabled
-- default; a NULL assessmentTypes value means all types currently used in that class.";

// v0.10.00
$count++;
$sql[$count][0] = "0.10.00";
$sql[$count][1] = "ALTER TABLE classStreamPost ADD COLUMN parentsCanView enum('Y','N') DEFAULT NULL AFTER pinned
;end
-- Parents-can-view toggle on Announcement/Material posts (NULL on every existing post means
-- visible, same as Y; only an explicit N hides one). A third post type, Homework, adds no column
-- here: it writes straight to core's gibbonPlannerEntry instead of classStreamPost, reusing the
-- homework card the Planner rows already render on the stream.";
