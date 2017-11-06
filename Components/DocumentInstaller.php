<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
        if (!$this->hasDocumentsInstalled()) {
            $this->uninstallDocuments();
            $this->insertDefaultDocuments();
        }
    }

    public function uninstallDocuments()
    {
        $sql = 'DELETE FROM `s_core_documents_box` WHERE `name` LIKE ?';

        $this->databaseConnection->query($sql, array('Paypal_%'));
    }

    /**
     * @return bool
     */
    private function hasDocumentsInstalled()
    {
        $sql = "SELECT id FROM s_core_documents_box WHERE name IN ('Paypal_Footer', 'Paypal_Content_Info')";

        return count($this->databaseConnection->executeQuery($sql)->fetchAll()) === 2;
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
               . 'Demo GmbH hat die Forderung gegen Sie im Rahmen eines laufenden Factoringvertrages an die PayPal (Europe) S.àr.l. et Cie, S.C.A. abgetreten. Zahlungen mit schuldbefreiender Wirkung können nur an die PayPal (Europe) S.àr.l. et Cie, S.C.A. geleistet werden.'
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
               . '</table>',
        ));
    }
}
