<?php

/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\SwagPaymentPaypalPlus\Components;

use Enlight_Components_Db_Adapter_Pdo_Mysql as DatabaseConnection;

class DocumentInstaller
{
    /** @var DatabaseConnection */
    private $databaseConnection;

    /**
     * DocumentInstaller constructor.
     *
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->databaseConnection = $db;
    }

    public function installDocuments()
    {
        $this->removeOldEntries();
        $this->insertDefaultDocuments();
    }

    private function removeOldEntries()
    {
        $sql = 'DELETE FROM `s_core_documents_box` WHERE `name` LIKE ?';

        $this->databaseConnection->query($sql, array('Paypal_%'));
    }

    private function insertDefaultDocuments()
    {
        $sql = "
			INSERT INTO `s_core_documents_box` (`documentID`, `name`, `style`, `value`) VALUES
			(1, 'Paypal_Footer', 'width: 170mm;\r\nposition:fixed;\r\nbottom:-20mm;\r\nheight: 15mm;', ?),
			(1, 'Paypal_Content_Info', ?, ?);
		";

        $this->databaseConnection->query($sql, array(
            '<table style="height: 90px;" border="0" width="100%">'
            . '<tbody>'
            . '<tr valign="top">'
            . '<td style="width: 33%;">'
            . '<p><span style="font-size: xx-small;">Demo GmbH</span></p>'
            . '<p><span style="font-size: xx-small;">Steuer-Nr: <br/>UST-ID: <br/>Finanzamt </span><span style="font-size: xx-small;">Musterstadt</span></p>'
            . '</td>'
            . '<td style="width: 33%;">'
            . '<p><span style="font-size: xx-small;">AGB<br /></span></p>'
            . '<p><span style="font-size: xx-small;">Gerichtsstand ist Musterstadt<br />Erf&uuml;llungsort Musterstadt</span></p>'
            . '</td>'
            . '<td style="width: 33%;">'
            . '<p><span style="font-size: xx-small;">Gesch&auml;ftsf&uuml;hrer</span></p>'
            . '<p><span style="font-size: xx-small;">Max Mustermann</span></p>'
            . '</td>'
            . '</tr>'
            . '</tbody>'
            . '</table>',
            '.payment_instruction, .payment_instruction td, .payment_instruction tr {'
            . '	margin: 0;'
            . '	padding: 0;'
            . '	border: 0;'
            . '	font-size:8px;'
            . '	font: inherit;'
            . '	vertical-align: baseline;'
            . '}'
            . '.payment_note {'
            . '	font-size: 10px;'
            . '	color: #333;'
            . '}',
            '<div class="payment_note">'
               . '<br />'
               . 'Warum PayPal? Rechnungskauf ist ein Service für den wir mit PayPal zusammenarbeiten. Der Betrag wurde von PayPal soeben direkt an uns gezahlt. Sie bezahlen den Rechnungsbetrag gemäß den Zahlungshinweisen an PayPal, nachdem Sie die Ware erhalten und geprüft haben.'
               . '<br /><br />'
               . 'Bitte überweisen Sie {$instruction.amount_value|currency} bis {$instruction.payment_due_date|date_format: "%d.%m.%Y"} an PayPal.'
               . '<br /><br />'
               . '</div>'
               . '<table class="payment_instruction">'
               . '<tbody>'
               . '<tr>'
               . '<td>Empf&auml;nger:</td>'
               . '<td>{$instruction.account_holder_name}</td>'
               . '</tr>'
               . '<tr>'
               . '<td>IBAN:</td>'
               . '<td>{$instruction.international_bank_account_number}</td>'
               . '</tr>'
               . '<tr>'
               . '<td>BIC:</td>'
               . '<td>{$instruction.bank_identifier_code}</td>'
               . '</tr>'
               . '<tr>'
               . '<td>Bank:</td>'
               . '<td>{$instruction.bank_name}</td>'
               . '</tr>'
               . '<tr>'
               . '<td>Betrag:</td>'
               . '<td>{$instruction.amount_value}&nbsp;{$instruction.amount_currency}</td>'
               . '</tr>'
               . '<tr>'
               . '<td>Verwendungszweck:</td>'
               . '<td>{$instruction.reference_number}</td>'
               . '</tr>'
               . '</tbody>'
               . '</table>'
        ));
    }
}
