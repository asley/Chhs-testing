<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mapping_person.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('juss-examBridge'), 'index.php');
    $page->breadcrumbs->add(__('Bridge Mappings'), 'mappings.php');
    $page->breadcrumbs->add(__('Person Mappings'));

    $form = Form::create('personMap', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/mapping_personProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'upsert');

    $people = [];
    try {
        $sql = "
            SELECT gibbonPersonID, surname, firstName, preferredName, email, studentID
            FROM gibbonPerson
            ORDER BY surname, firstName
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $label = trim($row['surname'] . ', ' . ($row['preferredName'] ?: $row['firstName']));
            if (!empty($row['email'])) {
                $label .= ' | ' . $row['email'];
            }
            if (!empty($row['studentID'])) {
                $label .= ' | ' . $row['studentID'];
            }
            $people[(int) $row['gibbonPersonID']] = $label;
        }
    } catch (PDOException $e) {
        $page->addError(__('Unable to load people list.'));
    }

    $row = $form->addRow();
    $row->addLabel('externalUserId', __('External User ID'));
    $row->addTextField('externalUserId')->maxLength(100)->required();

    $row = $form->addRow();
    $row->addLabel('externalEmail', __('External Email'));
    $row->addEmail('externalEmail')->maxLength(255);

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Gibbon Person'));
    $row->addSelect('gibbonPersonID')->fromArray($people)->required()->placeholder();

    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')->fromArray([
        'active' => __('Active'),
        'inactive' => __('Inactive'),
    ])->selected('active')->required();

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Save Mapping'));

    echo '<h2>' . __('Add or Update Person Mapping') . '</h2>';
    echo $form->getOutput();

    echo '<h2>' . __('Current Person Mappings') . '</h2>';
    echo '<table class="smallIntBorder fullWidth colorOddEven">';
    echo '<tr><th>' . __('External User ID') . '</th><th>' . __('External Email') . '</th><th>' . __('Person') . '</th><th>' . __('Status') . '</th><th>' . __('Actions') . '</th></tr>';

    try {
        $listSql = "
            SELECT pm.gibbonJussExamBridgePersonMapID, pm.externalUserId, pm.externalEmail, pm.status,
                   p.gibbonPersonID, p.surname, p.firstName, p.preferredName
            FROM gibbonJussExamBridgePersonMap pm
            LEFT JOIN gibbonPerson p ON p.gibbonPersonID = pm.gibbonPersonID
            ORDER BY pm.externalUserId
        ";
        $listStmt = $connection2->prepare($listSql);
        $listStmt->execute();

        while ($row = $listStmt->fetch(PDO::FETCH_ASSOC)) {
            $personLabel = empty($row['gibbonPersonID'])
                ? __('Missing Person')
                : sprintf('%s (%s, %s)', $row['gibbonPersonID'], $row['surname'], $row['preferredName'] ?: $row['firstName']);

            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['externalUserId']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['externalEmail']) . '</td>';
            echo '<td>' . htmlspecialchars($personLabel) . '</td>';
            echo '<td>' . htmlspecialchars($row['status']) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/mapping_personProcess.php" style="display:inline">';
            echo '<input type="hidden" name="address" value="' . htmlspecialchars($session->get('address')) . '">';
            echo '<input type="hidden" name="action" value="delete">';
            echo '<input type="hidden" name="gibbonJussExamBridgePersonMapID" value="' . (int) $row['gibbonJussExamBridgePersonMapID'] . '">';
            echo '<button type="submit" onclick="return confirm(\'' . __('Are you sure?') . '\');">' . __('Delete') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    } catch (PDOException $e) {
        echo '<tr><td colspan="5">' . __('Unable to load mappings.') . '</td></tr>';
    }

    echo '</table>';
}
