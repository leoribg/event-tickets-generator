<?php
/**
 *  Ticket Class
 */
namespace EventTicket;

use Endroid\QrCode\QrCode;
use Picqer\Barcode\BarcodeGeneratorPNG;
use \ZipArchive;
 
/**
 *  Cette classe regroupe les fonctionnalitées principales de la librairie
 *  
 *  @author  Nyzo
 *  @version 1.0.3
 *  @license CC-BY-NC-SA-4.0 Creative Commons Attribution Non Commercial Share Alike 4.0
 */
class Ticket {
	
	private $generator, $codes, $zip, $qrcode, $barcode, $error, $path_tickets = 'tickets', $use_qr_links = false;
	
	private $event = ['event_logo' => null, 'event_name' => null, 'event_orga_name' => null, 'event_location' => null, 'event_date' => null];
	
	/**
	 *  Initialiser la classe et charger les librairies. Certains paramètres généraux relatifs aux tickets peuvent être définit
	 *
	 *  @param string $lang Définit la langue à utiliser pour le script
	 */
	public function __construct($lang = 'fr-FR'){
		putenv('LC_ALL=' . $lang);
        setlocale(LC_ALL, $lang);
		bindtextdomain('eventTickets', "/lang");
		textdomain("eventTickets");
		
		$this->generator = new Generator;
		$this->zip       = new ZipArchive;
		$this->qrcode    = new QrCode();
		$this->barcode   = new BarcodeGeneratorPNG();
		$this->error     = new Error($lang);
		
		if(!is_dir(TEMP)) mkdir(TEMP);
		if(!is_dir('tickets')) mkdir('tickets');
	}
	
	/**
	 * Définir des variables concernant l'événement
	 *
	 * @param string $varName Définit le nom de la variable
	 * @param string $value   Assigne une valeur à la variable
	 */
	public function __set($varName, $value = null){
		if($varName == 'event_date')
			$this->event[$varName] = date('d/m/Y H:i:s', strtotime($value));
        $this->event[$varName] = $value;
    }
	
	/**
	 *  Supprimer le dossier temporaire et son contenu
	 */
	private function cleanTemp(){
		$files = glob(TEMP . '*');
		foreach($files as $file){
		    if(is_file($file))
			    unlink($file);
		}
		rmdir(TEMP);
	}
	
	/**
	 *  Initialiser le modèle du ticket au format PDF. Les paramètres généraux sont définit au préalable au sein de la classe
	 *
	 *  @param array  $params   Contient différents paramètres personnalisables mais requis pour le template 
	 *  @param string $template Permet de définir globalement le template à utiliser
	 */
	public function setTemplate($params, $template = 'BasicTicket'){
		$this->generator->params = $params;
	}
	
	/**
	 *  Générer un ou plusieurs tickets au format PDF à partir d'un tableau. Ne retourne pas d'erreurs si un camps est manquant
	 *  
	 *  @param array  $tickets         Contient le(s) ticket(s) eux-même dans des tableaux
	 *  @param bool   $multiple_file   Permet d'enregistrer sous plusieurs fichiers chaque billets (pas de rendu)
	 *  @param bool   $display         Rendu direct du PDF (affichage dans la naviguateur en appelant la fonction)
	 *  @param string $ticket_template Template du ticket à utiliser pour la génération de ces tickets uniquement (peut être définit globalement)
	 *  @param bool   $use_qr_links    Permet d'assigner le lien définit dans le ticket au code Qr, il est possible de définir ce paramètre en général ($this->use_qr_links)
	 *  
	 *  return array Retourne le code de chaque ticket généré ainsi que l'information si les tickets sont dans un seul fichiers ou plusieurs
	 */
	public function genTickets($tickets, $multiple_file = false, $display = true, $ticket_template = 'BasicTicket', $use_qr_links = false){
		
		if(!method_exists($this->generator, $ticket_template))
			$this->error->echoError(1);
		
		if(empty($tickets))
			$this->error->echoError(2);
		
		if(isset($tickets['ticket_code'])){
			$current_ticket = $tickets;
			unset($tickets);
			$tickets[] = $current_ticket;
		}
		
		$tickets_code = [];
		foreach($tickets as $ticket){
			if(empty($ticket['ticket_code']))
			    $this->error->echoError(3);
		
			if(!empty($this->codes)){
				if(!in_array($ticket['ticket_code'], $this->codes))
					$this->error->echoError(4);
			}
			
			$ticket = $this->cleanTicket($ticket);
			
			$qr_content = $ticket['ticket_code'];
			if($this->use_qr_links === true || $use_qr_links === true)
				$qr_content = (isset($ticket['link_validation']) && !filter_var($ticket['link_validation'], FILTER_VALIDATE_URL) === false) ? $ticket['link_validation'] : $ticket['ticket_code'];
			
			$this->generator->setData(['event' => (object) $this->event, 'ticket' => (object) $ticket], $this->genQrCode($qr_content), $this->genBarCode($ticket['ticket_code']));
			$this->generator->AddPage();
			$this->generator->$ticket_template();
			
			$tickets_code[] = $ticket['ticket_code'];
			
			if($multiple_file === true){
				$this->generator->Output('F', 'tickets/' . $ticket['ticket_code'] . '.pdf');
				$this->generator = new Generator;
			    $this->setGenerator();
			}
		}
	
	    $tickets_code['multiple_file'] = true;
	    if($multiple_file === false){
			$file_name = 'tickets/tickets' . rand() . '.pdf';
			$this->generator->Output('F', $file_name);
			$tickets_code['multiple_file'] = $file_name;
		}
		
		if($multiple_file === false && $display === true)
			$this->generator->Output();
		
		$this->cleanTemp();
		
		return $tickets_code;
	}
	
	/**
	 *  Générer un QrCode
	 *  
	 *  @param string|int $data Code du ticket ou lien de validation
	 *  
	 *  @return string Lien image du QrCode
	 */
	private function genQrCode($data){
		$ticket_code_file = is_int($data) ? $data . rand() : rand();
		$this->qrcode
			->setText($data)
			->setSize(200)
			->setPadding(20)
			->setErrorCorrection('high')
			->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0])
			->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0])
			->setImageType(QrCode::IMAGE_TYPE_PNG)
		;

		$this->qrcode->save(TEMP . $ticket_code_file . '.png');
		
		return TEMP . $ticket_code_file . '.png';
	}
	
	/**
	 *  Générer un code-barre
	 *  
	 *  @param string|int $ticket_code Code du ticket
	 *  
	 *  @return string Lien image du code-barre
	 */
	private function genBarCode($ticket_code){
		$ticket_code_file = $ticket_code . rand();
		file_put_contents(TEMP . $ticket_code_file . '.png', $this->barcode->getBarcode($ticket_code, $this->barcode::TYPE_CODE_128));
		
		return TEMP . $ticket_code_file . '.png';
	}
	
	/**
	 *  Recréer un ticket normé au script
	 *  
	 *  @param array $ticket Ticket(s)
	 *  
	 *  @return string Ticket correctement formatté
	 */
	private function cleanTicket($ticket){
		if(!is_array($ticket))
			$this->error->echoError(8);
		
		// Propriétés d'un ticket-type
		$vars = ['ticket_code', 'event_id', 'user_id', 'user_first_name', 'user_last_name', 'ticket_type', 'ticket_price', 'ticket_buy_date', 'link_validation'];
		
		// Propriétés d'un ticket-type (et les possibiltées)
		$ticket_code     = ['ticket_code', 'code', 'code_ticket', 'code_billet', 'billet_code'];
		$event_id        = ['event_id', 'eventid', 'idevent', 'id_event', 'event'];
		$user_id         = ['user_id', 'id_user', 'uid', 'u_id', 'id'];
		$user_first_name = ['user_first_name', 'firstname', 'first_name', 'prenom', 'name'];
		$user_last_name  = ['user_last_name', 'lastname', 'last_name', 'nom'];
		$ticket_type     = ['ticket_type', 'type_ticket', 'tickettype', 'typeticket', 'type', 'type_billet', 'billet_type', 'typebillet', 'billettype'];
		$ticket_price    = ['ticket_price', 'price_ticket', 'price', 'ticketprice', 'priceticket', 'prix', 'billet_prix', 'prix_billet', 'billetprix', 'prixbillet'];
		$ticket_buy_date = ['ticket_buy_date', 'ticket_buy', 'ticketbuydate', 'buy_date', 'buydate', 'date_buy', 'date_achat', 'date_achat_billet'];
		$link_validation = ['link_validation', 'link', 'lien_validation', 'validation', 'lien', 'validation_lien', 'lienvalidation', 'validation', 'valid', 'validation_link'];
		
		foreach($vars as $var){
			if(isset($var) && is_array(${$var})){
				foreach(${$var} as $propretie){
					if(array_key_exists($propretie, $ticket))
						${$var} = $ticket[$propretie];
				}
			}
		}
		
		$new_ticket = [];
		$new_ticket = [
			'ticket_code'     => !is_array($ticket_code) ? $ticket_code : 'N/A',
			'event_id'        => !is_array($event_id) ? $event_id : 'N/A',
			'user_first_name' => !is_array($user_first_name) ? $user_first_name : 'N/A',
			'user_last_name'  => !is_array($user_last_name) ? $user_last_name : 'N/A',
			'ticket_type'     => !is_array($ticket_type) ? strtoupper($ticket_type) : 'N/A',
			'ticket_price'    => !is_array($ticket_price) ? $ticket_price : 'N/A',
			'ticket_buy_date' => !is_array($ticket_buy_date) ? $ticket_buy_date : 'N/A',
			'link_validation' => !is_array($link_validation) ? $link_validation : 'N/A',
		];
		return $new_ticket;
	}
	
	/**
	 *  Importer un/des ticket(s) dans un tableau, issu(s) d'un fichier CSV
	 *  
	 *  @param string|array $file Chemin du fichier à importer
	 *
	 *  @return array Ticket(s) importé(s) et normé(s)
	 */
	public function importTickets($file){
		$this->checkFile($file, ['csv']);
		
		$tickets = [];
		$i = 1;
		$j = 0;
		if(is_array($file)){
			foreach($file as $current_file){
				$temp_tickets = [];
				$current_file = fopen($current_file, 'r');
				while(($line = fgetcsv($current_file)) !== FALSE){
					$line = array_map("utf8_encode", $line);
					if($i == 1)
						$head = explode(';', $line[0]);
					else // Supprime l'en-tête
						$temp_tickets[] = array_combine($head, array_values(explode(';', $line[0])));
					++$i;
				}
				fclose($current_file);
				
				foreach($temp_tickets as $ticket){
					$tickets[$j] = $this->cleanTicket($ticket);
					++$j;
				}
			}
		}else {
			$file = fopen($file, 'r');
			while(($line = fgetcsv($file)) !== FALSE){
				$line = array_map("utf8_encode", $line);
				if($i == 1)
					$head = explode(';', $line[0]);
				else // Supprime l'en-tête
					$tickets[] = array_combine($head, array_values(explode(';', $line[0])));
				++$i;
			}
			fclose($file);
			
			$i = 0;
			foreach($tickets as $ticket){
				$tickets[$i] = $this->cleanTicket($ticket);
				++$i;
			}
		}
		
		
		return $tickets;
	}
	
	/**
	 *  Exporter un/des ticket(s) au format CSV, issu(s) d'un tableau
	 *  
	 *  @param array  $tickets   Tableau contenant le(s) ticket(s)
	 *  @param bool   $download  Définit si le fichier doit être directement téléchargé
	 *  @param bool   $save      Définit si le fichier doit être sauvegardé sur le serveur (sans être téléchargé)
	 *  @param bool   $head      Permet de définir un en-tête propre ou un en-tête "prêt à importer" (valeur par défaut recommandée)
	 *  @param string $separator Définit le séparateur pour les lignes (valeur par défaut recommandée)
	 */
	public function exportTickets($tickets, $download = true, $save = false, $head = true, $separator = ';'){
		$filename = 'tickets' . rand(100, 999) . '.csv';
		
		if($download === true){
			header("Content-Type: text/csv; charset=UTF-8");
		    header("Content-Disposition: attachment; filename=" . $filename);
		}
		
		$head = ($head === true) ? $head = ["id", "ticket_code", "event_id", "user_first_name", "user_last_name", "ticket_type", "ticket_price", "ticket_buy_date"] : ["", "Code", "Prénom", "Nom", "Date", "Type", "Prix", "Date d'achat"];
	    
		$cells = [];
		$i = 0;
		foreach($tickets as $ticket){
			if(!empty($this->codes)){
				if(!in_array($ticket['ticket_code'], $this->codes))
					$this->error->echoError(4);
			}
			$new_ticket = [];
			$new_ticket['id'] = $i;
			$new_ticket = [
			    'id' => $i,
				'ticket_code'     => isset($ticket['ticket_code']) ? $ticket['ticket_code'] : null,
				'event_id'        => isset($ticket['$event_id']) ? $ticket['$event_id'] : null,
				'user_first_name' => isset($ticket['user_first_name']) ? $ticket['user_first_name'] : null,
				'user_last_name'  => isset($ticket['user_last_name']) ? $ticket['user_last_name'] : null,
				'ticket_type'     => isset($ticket['ticket_type']) ? $ticket['ticket_type'] : null,
				'ticket_price'    => isset($ticket['ticket_price']) ? $ticket['ticket_price'] : null,
				'ticket_buy_date' => isset($ticket['ticket_buy_date']) ?  date('d/m/Y', strtotime($ticket['ticket_buy_date'])) : null,
			];
			$cells[] = $new_ticket;
			$i++;
		}
		
		if($download === true){
			echo implode($separator, $head) . "\r\n";

			foreach ($cells as $cell)
				echo implode($separator, $cell) . "\r\n";
		}
			
		if($save === true || $download == false){
			file_put_contents($filename, implode($separator, $head));
			
			$fp = fopen($filename, 'w');
			
			fputcsv($fp, $head);

			foreach($cells as $cell)
				fputcsv($fp, $cell);

			fclose($fp);
		}
	}
	
	/**
	 *  Définir des codes de tickets valides. Ces codes seront comparés aux tickets générés afin de valider leurs authenticité
	 *  
	 *  @param array $codes Tableau contenant les codes valides
	 */
	public function setCodes($codes){
		$this->codes = $codes;
	}
	
	/**
	 *  Récupérer la liste des codes valides
	 *  
	 *  @return array Retourne la liste des codes
	 */
	public function getCodes(){
		return $this->codes;
	}
	
	/**
	 *  Télécharger le(s) ticket(s) au format PDF. Si plusieurs tickets sont à télécharger, ils seront regroupés dans un dossier compressé au format ZIP
	 *  
	 *  @param string $file    Chemin vers le(s) fichier(s) PDF à télécharger (si déjà enregistrés)
	 *  @param array  $tickets Ticket(s) provenant d'un tableau à télécharger directement
	 *  
	 *  @see genTickets()
	 */
	public function downloadTickets($file = null, $tickets = null){
		if($file != null)
			$this->checkFile($file, 'pdf');
		elseif($tickets != null && is_array($tickets)){
			$file = $this->genTickets($tickets);
			$this->checkFile($file, 'pdf');
		}else $this->error->echoError(5);
		
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Description: File Transfer');
		header('Connection: Keep-Alive');
        header('Expires: 0');
		header('Pragma: public');
		
		if(is_array($file)){
			$this->zip->open('tickets/tickets.zip', $this->zip::CREATE);
			foreach ($file as $tickets){
				$this->checkFile($tickets, 'pdf');
				$this->zip->addFile('tickets/' . $tickets);
			}
			$this->zip->close();
			
			$file = 'tickets/tickets.zip';
			
			header('Content-Type: application/zip');
			header("Content-disposition: attachment; filename='tickets.zip'");
			header('Content-Length: ' . filesize($file));
			readfile($file);
		}else {
			$file = 'tickets/' . $file;
			// Vérifie que le .pdf est bien présent, sinon on le rajoute
			if(substr($file, -4) != '.pdf')
				$file = $file . '.pdf';
			
			header('Content-Type: application/octetstream');
            header("Content-Transfer-Encoding: Binary"); 
			header("Content-length: " . filesize($file));
            header("Content-Disposition:attachment;filename='ticket.pdf'");
            readfile($file);
		}
		
		ignore_user_abort(true);
		if(connection_aborted())
			unlink($file);
	}
	
	/**
	 *  Vérifier qu'un fichier existe et que son format est valide selon la demande
	 *  
	 *  @param string|array $file Chemin vers le fichier
	 *  @param string|array $type Extension à vérifier
	 *  @param string       $path Dossier contenant le(s) fichier(s)
	 *  
	 *  @return true
	 */
	private function checkFile($file, $type = 'csv', $path = 'tickets'){
		if($path != null)
			$path = $path . '/';
		
		if(is_array($file)){
			foreach($file as $ticket){
				if(empty($ticket) && !file_exists($path . $ticket))
			        $this->error->echoError(6);
				
				if(is_array($type)){
					if(!in_array(pathinfo($path . $ticket, PATHINFO_EXTENSION), $type))
						$this->error->echoError(7, [$type]);
				}elseif(pathinfo($path . $ticket, PATHINFO_EXTENSION) != $type)
			        $this->error->echoError(7, [$type]);
			}
		}else {
			if(empty($file) && !file_exists($path . $file))
			    $this->error->echoError(6);
			
			if(is_array($type)){
				if(!in_array(pathinfo($path . $file, PATHINFO_EXTENSION), $type))
					$this->error->echoError(7, [$type]);
			}elseif(pathinfo($path . $file, PATHINFO_EXTENSION) != $type)
				$this->error->echoError(7, [$type]);
		}
		
		return true;
	}
}
