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

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/settings.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Class Stream Settings'));

$settingGateway = $container->get(SettingGateway::class);

$form = Form::create('settings', $session->get('absoluteURL').'/modules/'.$session->get('module').'/settingsProcess.php');
$form->setFactory(\Gibbon\Forms\DatabaseFormFactory::create($pdo));
$form->addHiddenValue('address', $session->get('address'));

$form->addRow()->addHeading(__m('Students'));

$setting = $settingGateway->getSettingByScope('Class Stream', 'defaultStudentAccess', true);
$row = $form->addRow();
    $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
    $row->addSelect($setting['name'])->fromArray([
        'Post'    => __m('Students can post and comment'),
        'Comment' => __m('Students can only comment'),
        'None'    => __m('Only teachers can post or comment'),
    ])->selected($setting['value'])->required();

$form->addRow()->addHeading(__m('Planner'));

$setting = $settingGateway->getSettingByScope('Class Stream', 'showHomework', true);
$row = $form->addRow();
    $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

$setting = $settingGateway->getSettingByScope('Class Stream', 'showLessons', true);
$row = $form->addRow();
    $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

$form->addRow()->addHeading(__m('Parents'));

$setting = $settingGateway->getSettingByScope('Class Stream', 'parentView', true);
$row = $form->addRow();
    $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
    $row->addSelect($setting['name'])->fromArray([
        'Own child'    => __m('Own child only: teacher posts, plus posts and comments by their own child'),
        'All redacted' => __m('All posts and comments, other students\' names and photos hidden'),
        'None'         => __m('Teacher posts only, no student posts or comments'),
    ])->selected($setting['value'])->required();

$form->addRow()->addHeading(__m('Notifications'));

$setting = $settingGateway->getSettingByScope('Class Stream', 'notifyOnPost', true);
$row = $form->addRow();
    $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
