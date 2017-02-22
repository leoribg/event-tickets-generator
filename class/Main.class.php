<?php
/**
 *  Main Class
 */
 
use Importer, Generator, ZipArchive;
 
/**
 *  Cette classe regroupe les fonctionnalitées principales de la librairie
 *  
 *  @author  Nyzo
 *  @version 1.0
 *  @license CC-BY-NC-SA-4.0 Creative Commons Attribution Non Commercial Share Alike 4.0
 */
class Main {
	
	private $importer, $generator, $codes, $zip;
	
	public $event_logo, $event_name, $event_orga_name, $event_location, $path_tickets = 'tickets';
	
	/**
	 *  Initialise la classe et charge les librairies. Certains paramètres généraux relatifs aux tickets peuvent être définit
	 */
	public function __construct($event_logo = null, $event_name = null, $event_orga_name = null, $event_location = null, Importer $importer, Generator $generator, ZipArchive $zip){
		$this->importer  = new $importer();
		$this->generator = new $generator();
		$this->zip       = new $zip;
		
		$this->event_logo      = $event_logo;
		$this->event_name      = $event_name;
		$this->event_orga_name = $event_orga_name;
		$this->event_location  = $event_location;
	}
	
	/**
	 *  Initialise le modèle du ticket au format PDF. Les paramètres généraux sont définit au préalable au sein de la classe
	 */
	public function setGenerator(){
		$this->generator->logo = $event_logo;
	}
	
	/**
	 *  Générer un unique ticket au format PDF
	 */
	public function genTicket($user_first_name, $user_last_name, $event_date, $ticket_type, $ticket_price, $ticket_buy_date, $ticket_code){
		if(!empty($this->codes)){
			if(!in_array($ticket_code, $this->codes))
				throw new Exception('Des codes de tickets sont définis mais le ticket actuel n\'en a pas ou le code n\'est pas valide.');
		}
		
		$this->generator->Output();
		
	}
	
	/**
	 *  Générer un ou plusieurs tickets au format PDF à partir d'un tableau. Ne retourne pas d'erreurs si un camps est manquant
	 *  
	 *  @param array $tickets Contient le(s) ticket(s) eux-même dans des tableaux
	 *  
	 *  return array Retourne le nom de chaque ticket généré
	 */
	public function genTickets($tickets[]){
		$this->setGenerator();
		
		foreach($tickets as $ticket){
			
		}
		
		return $tickets_file_name;
	}
	
	/**
	 *  Importer un/des ticket(s) dans un tableau, issu(s) d'un fichier CSV
	 *  
	 *  @param string $file Chemin du fichier à importer
	 */
	public function importTicket($file){
		$this->checkFile($file);
		
		$tickets = [];
		$i = 1;
		$file = fopen($file, 'r');
		while (($line = fgetcsv($file)) !== FALSE){
			if($i != 1)
		        $tickets[] = $line;
			
			$i++;
		}
		fclose($file);
		
		return $tickets;
	}
	
	/**
	 *  Exporter un/des ticket(s) au format CSV, issu(s) d'un tableau
	 *  
	 *  @param array  $tickets   Tableau contenant le(s) ticket(s)
	 *  @param bool   $head      Permet de définir un en-tête propre ou un en-tête "prêt à importer" (valeur par défaut recommandée)
	 *  @param string $separator Définit le séparateur pour les lignes (valeur par défaut recommandée)
	 */
	public function exportTickets($tickets[], $head = true, $separator = ';'){
		header("Content-Type: text/csv; charset=UTF-8");
		header("Content-Disposition: attachment; filename=tickets.csv");
		
		$head = $head === true ? $head = ["id", "ticket_code", "user_first_name", "user_last_name", "event_date", "ticket_type", "ticket_price", "ticket_buy_date"] : ["", "Code", "Prénom", "Nom", "Date", "Type", "Prix", "Date d'achat"];
	    
		$cells = [];
		foreach($tickets as $ticket){
			$cells[] = $ticket;
		}
		
		echo implode($separator, $entete) . "\r\n";

        foreach ($cells as $cell) {
	        echo implode($separator, $cell) . "\r\n";
        }
	}
	
	public function setCodes($data[]){
		$this->codes = $data;
	}
	
	/**
	 *  Télécharger le(s) ticket(s) au format PDF. Si plusieurs tickets sont à télécharger, ils seront regroupés dans un dossier compressé au format ZIP
	 *  
	 *  @param string $file    Chemin vers le(s) fichier(s) PDF à télécharger (si déjà enregistrés)
	 *  @param array  $tickets Ticket(s) provenant d'un tableau à télécharger directement
	 *  
	 *  @see genTickets()
	 */
	public function downloadTicket($file = null, $tickets[] = null){
		if($file != null){
			$this->checkFile($file);
		}else {
			$file = $this->genTickets($tickets);
		}
		
		if(is_array($file)){
			$this->zip->open('tickets.zip', ZipArchive::CREATE);
			foreach ($file as $tickets) {
			  $zip->addFile($tickets);
			}
			$this->zip->close();
			
			header('Content-Type: application/zip');
			header("Content-disposition: attachment; filename='tickets.zip'");
			header('Content-Length: ' . filesize('tickets.zip'));
			readfile('tickets.zip');
		}else {
			header("Content-type:application/pdf"); 
            header("Content-Disposition:attachment;filename='ticket.pdf'");
            readfile($this->path_tickets . $file . '.pdf');
		}
	}
	
	/**
	 *  Vérifier qu'un fichier existe et que son format est valide selon la demande
	 *  
	 *  @param string $file Chemin vers le fichier
	 *  @param string $type Extension à vérifier
	 */
	private function checkFile($file, $type = 'csv'){
		if(empty($file) && !file_exists($file))
			throw new Exception('Le fichier n\'existe pas.');
		
		if(pathinfo($file, PATHINFO_EXTENSION) != $type)
			throw new Exception("Le fichier à importer n'est pas au format {$type}.");
		
		return true;
	}
}