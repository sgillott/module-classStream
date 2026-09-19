# Class Stream

A Classroom-style stream for every class in Gibbon. Teachers post announcements and materials with links and files, class members comment, and the homework set in the Planner shows up on the same stream. Each class has its own colour, header image and settings.

Requires Gibbon `v30.0.00` or later.


## What the Module Does

- **One stream per class.** Every class gets a stream. Teachers, assistants and technicians of the class run it; students of the class read and, if the class allows, comment or post.
- **Posts.** Two kinds: an *Announcement* (rich text - default) and a *Material* (rich text but with a title). Both can carry web links and uploaded files. A post can be pinned to the top, published now, saved as a draft, or scheduled for a date and time.
- **Comments.** Plain-text class comments under each post and under each Planner item. Comments are stored in Gibbon's own discussion table.
- **Planner on the stream.** Homework set in the Planner appears on the stream automatically, as a row of its own, at the lesson's date. Planned lessons can appear too if the school allows it. Nothing is copied: the stream reads the Planner live, so a change in the Planner is on the stream at once. An *Upcoming* panel lists the homework still due.
- **Class Settings.** Each class teacher chooses the theme colour (16 to pick from), a header image, what students may do (post and comment / comment only / nothing), how Planner items show (condensed rows, rows with details, or hidden), and which other classes this one syncs with.
- **Sync between classes.** *1-way sync* copies every published announcement and material from this class into the chosen classes. *2-way sync* does it in both directions. Comments never travel. Copies follow edits and deletions of the original.
- **Reuse.** Copy posts from any class you have taught, this year or a past one, into the current class. They arrive as drafts for you to publish or schedule one by one.
- **Drafts.** A page per class listing its drafts and scheduled posts, with Edit, Publish Now and Delete.
- **People.** The class roster: each student's last visit to the stream, number of visits, comments and posts, with a Mute box per student (and one for everyone). A muted student can read the stream but not post or comment in that class.
- **Parents.** Read only, through the lens the school chooses: their own child's posts and comments only; everything with other students' names and photos hidden; or teacher posts only.
- **Heads of Department.** A permission that adds read-and-comment access to every class of the departments the person is in charge of.
- **Notifications.** Class members get a Gibbon notification (and an email, if they have notification emails switched on) when a post is published.
- **New-post counts.** The class cards show how many posts are new since you last opened that stream.


### Dashboard Hooks

The module adds a **Class Stream** tab to the Staff, Student and Parent dashboards.

- When one of your classes is on the timetable *right now*, the tab shows that class's whole stream, with a "You are in 10B.Ma now" banner. This uses the school's timetable, so nothing has to be set up.
- Otherwise the tab shows a "Now" strip (if two lessons overlap, for example a cover) or a "Next" strip for the next lesson today, then your class cards.
- Parents see the cards of the child whose dashboard it is: Jack's dashboard tab shows Jack's classes, Jill's shows Jill's.

The tab can be made the landing tab in *School Admin > Dashboard Settings*.


# Main Pages

**My Streams** (`Class Stream > My Streams`) – one card per class you belong to this year, with the teachers, the number of posts and how many are new to you. Admins get a *Find a Class* box for any class; Heads of Department get a *Find a Department Class* box for the classes of their departments. A parent chooses a child first (if they have more than one) and the page is headed with that child's name, *Jack's Streams*, since the streams are the child's.

**The stream** – open a card. The banner shows the class; teachers see *Class Settings*, *People* and *Drafts* buttons on it. The left panel lists the teachers, the homework still due (*Upcoming*) and quick links to the Planner, Homework, Participants and Markbook. The main column is the stream: *Announce something to your class* at the top, then posts and Planner rows, newest first, pinned posts first. Fifteen items a page.

**Posting** – choose *Announcement* or *Material* (a Material has a title, otherwise it is exactly the same as an announcement post), write the post, add links (one per line) and files, tick *Pin to top* if you like, and choose when it publishes:

- *Now* – it goes on the stream and the class is notified.
- *Save as draft* – it goes to the Drafts page.
- *Schedule for a date and time* – it goes to the Drafts page as "Scheduled" and appears on the stream at that time.

**Editing** – the pencil icon on a post. You can add links and files, remove attachments one by one, change the type or the title, and change when it publishes. Editing a post that is already on the stream keeps its original time.

**Comments** – the box under each post or Planner row. The author of a comment, or a teacher of the class, can delete it.

**Class Settings** – the button on the banner. Appearance (colour, header image), Stream (what students may do, how Planner items show) and Linked Classes (1-way and 2-way sync). Only classes you teach are offered as sync targets.

**People** – the teachers, then a summary strip (how many students visited in the last 7 days, how many never, how many not for 30 days, comments in all) and the students: last visited (*Never* in red, more than 30 days ago in amber), visits, comments, posts, and a Mute box per student with a box in the header that mutes or unmutes everyone. Click a heading to sort. Save to apply the mutes. A visit is counted each time the student opens the stream, on the module pages or in the dashboard tab; nothing is recorded about what they read.

**Drafts** – the class's drafts and scheduled posts. *New Post* and *Reuse Posts from Another Class* buttons at the top; Edit, Publish Now and Delete on each row.

**Reuse** – pick a class you have taught (any year, with its post count), tick the posts, *Copy Selected*. The copies land in Drafts, in your name, with the same links and files.


### Notes

- A scheduled post is announced (notification and email) on the **first visit** to the stream after its time, by whoever visits – teacher, student, or the dashboard tab. If nobody opens the stream, the post is still there for anyone who does; only the announcement waits. There is no background job.
- Drafts and scheduled posts never sync to other classes. A post syncs when it is published; a scheduled one when its time comes.
- A synced copy shows a small "Synced from 10B.Ma" tag to staff. Edit it in the class it came from; editing the copy directly will be overwritten by the next edit of the original.
- Deleting an original also deletes its synced copies. A post that was *reused* into another class is that class's own post and stays.
- Uploaded files go into Gibbon's `uploads/` folder like every other module's files, and the file types allowed are the ones in *System Admin > File Extensions*. A file is removed from disk only when no post points at it any more.
- The Planner's own *Viewable to students* and *Viewable to parents* flags are honoured: a lesson hidden in the Planner is hidden on the stream.
- Parents never post or comment. Which posts and comments they see is a school-wide setting, not a per-class one. Links from a parent's stream into the Planner carry the child, so the Planner opens the right lesson for them.
- Timetable overlap: if two of your classes are on at the same time, the dashboard tab shows a strip for each rather than picking one.


# Settings

## Manage Settings

`Class Stream > Class Stream Settings` (admin):

| Setting | Meaning | Default |
|---|---|---|
| Default Student Access | What students may do in a class stream unless the class teacher changes it: post and comment, comment only, or nothing | Comment only |
| Show Homework | Homework set in the Planner appears on the stream | Yes |
| Show Lessons | Planned lessons (not only homework) may appear on the stream; each class teacher still chooses condensed, details or hidden | No |
| Parent View | What parents see besides teacher posts: their own child's posts and comments only; every post and comment with other students' names and photos hidden; or no student posts or comments at all | Own child |
| Notify On Post | Send a Gibbon notification to class members when a post is published | Yes |

Per-class settings live on each class's *Class Settings* page and are the teacher's.


### Before You Configure

**Permissions** (`User Admin > Manage Permissions`). The module installs one grouped action, *View Class Streams*, with four options, and a *Class Stream Settings* action:

| Option | Who it is for | What it gives |
|---|---|---|
| myClasses | Teachers, students (default) | The streams of the classes the person belongs to. Teachers of a class run it; students read and, if allowed, comment or post |
| departmentClassesView | Heads of Department (grant to their role) | Their own classes as above, plus read and comment in every class of the departments they are staff of (*School Admin > Manage Departments*). No posting, no settings, no moderation there |
| allClasses | Admin (default) | Any class, with full teacher rights in all of them. Also the *Find a Class* box on My Streams |
| myChildrensClasses | Parents (default) | Their children's streams, read only, through the Parent View setting |

Give a role only one of the four. *Class Stream Settings* is for admins.

**Departments.** departmentClassesView follows `gibbonCourse.gibbonDepartmentID` and `gibbonDepartmentStaff`: a course must belong to a department, and the Head must be listed as staff of it (any department role).

**Timetable.** The dashboard tab's "now" and "next" come from the active timetable for the current year. Without a timetable the tab simply shows the cards.

**Notification emails.** Emails follow each person's own *Receive notification emails* preference in Gibbon; the module does not send mail of its own.


## Installation

1. Copy the `Class Stream` folder into your Gibbon `modules/` directory.
2. In Gibbon go to *System Admin > Manage Modules* and click **Install** next to Class Stream. This creates the module's tables (`classStreamPost`, `classStreamPostAttachment`, `classStreamClass`, `classStreamMute`, `classStreamView`, `classStreamLink`), its settings, the permissions above, and the three dashboard hooks.
3. Check *User Admin > Manage Permissions* and grant *departmentClassesView* to your Head of Department role if you have one.
4. Set the school defaults in *Class Stream > Class Stream Settings*.

**Updating.** Replace the folder with the new version and click **Update** in *Manage Modules*. Updates carry the tables, permissions and hooks forward; nothing needs to be re-entered.

**Uninstalling.** *Manage Modules > Uninstall* removes the module's tables, settings and permissions. Comments live in Gibbon's `gibbonDiscussion` table (tagged with this module) and uploaded files stay in `uploads/`; remove them by hand if you want them gone.


## License

This module follows Gibbon's GPLv3 license.


## About

Class Stream module written by **Steve Gillott**

GitHub repository: [https://github.com/sgillott/module-classStream](https://github.com/sgillott/module-classStream) -- [Latest Release Version](https://github.com/sgillott/module-classStream/releases)

Gibbon: the flexible, open school platform  
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community ([https://gibbonedu.org/about/](https://gibbonedu.org/about/))   
Copyright © 2010, Gibbon Foundation  
Gibbon™, Gibbon Education Ltd. (Hong Kong)
