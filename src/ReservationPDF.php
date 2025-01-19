<?php
namespace Theosche\RoomReservation;

use setasign\Fpdi\Fpdi;

class ReservationPDF extends Fpdi {
    public function Header() {
        // Adresse de l'association
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 5, $this->utf8_to_iso8859_1(ORGANIZATION), 0, 1);
        $this->Cell(0, 5, $this->utf8_to_iso8859_1(ORGANIZATION_ADDRESS), 0, 1);
        $this->Cell(0, 5, $this->utf8_to_iso8859_1(ORGANIZATION_CITY), 0, 1);
        $this->SetTextColor(0, 0, 255); // Couleur du lien
        $this->SetFont('', 'U'); // Souligner le texte
        $this->Cell(0, 5, ORGANIZATION_EMAIL, 0, 1, '', false, "mailto:" . ORGANIZATION_EMAIL);
        $this->Ln(10);
    }
    public function prebookPDF($res) {
    	$left = 12;
    	$this->AddPage();
    	$this->SetMargins($left,10);
    	$this->SetLineWidth(0.5);
        
        // Adresse du demandeur
        $this->SetTextColor(0, 0, 0); // Réinitialiser la couleur
        $this->SetFont('Arial', '', 12);
        $this->SetXY(130, 40);
        $this->MultiCell(
            80,
            5,
            $this->utf8_to_iso8859_1(
                html_entity_decode($res->entite) . "\n" .
                $res->prenom . " " . $res->nom . "\n" .
                $res->adresse . "\n" .
                $res->npa . " " . $res->localite 
            )
        );
		$this->SetFont('Arial', 'U', 12); // Souligner le texte de l'email pour indiquer un lien
		$this->SetTextColor(0, 0, 255); // Couleur bleue pour le lien
		$this->SetX(130);
		$this->Write(
			5,
			$this->utf8_to_iso8859_1($res->email),
			'mailto:' . $res->email
		);
		$this->SetTextColor(0, 0, 0); // Retour à la couleur noire
		$this->SetFont('Arial', '', 12); // Réinitialiser la police
		$this->Ln(); // Petite marge avant le numéro de téléphone
		$this->SetX(130);
		// Ajout du téléphone
		$this->MultiCell(
			80,
			5,
			$this->utf8_to_iso8859_1($res->telephone)
		);

        // Titre
        $this->Ln(13);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $this->utf8_to_iso8859_1("Confirmation de pré-réservation " . OF_ROOM), 0, 1,'C');
        $this->Ln(8);

        // Date et lieu
        $this->SetFont('Arial', '', 12);
        $currentDate = new \DateTime();
        $this->Cell(0, 5, $this->utf8_to_iso8859_1(ORGANIZATION_CITY . ", le " . $currentDate->format('d.m.Y')), 0, 1);
        $this->Ln(8);
        
        $this->SetFont('Arial', 'B', 12);
        $this->MultiCell(185, 5, $this->utf8_to_iso8859_1(html_entity_decode($res->nom_evenement)));
		$this->Ln(2);
        $this->SetFont('Arial', '', 12);
        $this->MultiCell(185, 5, $this->utf8_to_iso8859_1(html_entity_decode($res->description_evenement)));
        $this->Ln(5);
        

        // Tableau des occurrences
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 6, $this->utf8_to_iso8859_1("Date"), 1);
        $this->Cell(15, 6, $this->utf8_to_iso8859_1("Début"), 1);
        $this->Cell(15, 6, $this->utf8_to_iso8859_1("Fin"), 1);
        $this->Cell(110, 6, $this->utf8_to_iso8859_1("Options"), 1);
        $this->Cell(25, 6, $this->utf8_to_iso8859_1("Prix"), 1, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $totalPrice = 0;
        
        $lineHeight = 5;
		$columnWidths = [20, 15, 15, 110, 25];
        foreach ($res->events as $event) {
			$text = $this->utf8_to_iso8859_1($event['text']);
			$price = number_format($event['price'], 2) . " " . CURRENCY;

			// Calcule la hauteur requise pour la cellule texte
			$nbLinesText = $this->NbLines($columnWidths[3], $text);
			$nbLines = max($nbLinesText, 1);

			$rowHeight = max($lineHeight * $nbLines, 7);
			if ($nbLines == 1) $lineHeight = $rowHeight;

			$this->Cell($columnWidths[0], $rowHeight, $event['start_time']->format('d.m.Y'), 1);
			$this->Cell($columnWidths[1], $rowHeight, $event['start_time']->format('H:i'), 1);
			$this->Cell($columnWidths[2], $rowHeight, $event['end_time']->format('H:i'), 1);

			$x = $this->GetX();
			$y = $this->GetY();
			$this->MultiCell($columnWidths[3], $lineHeight, $text, 1);
			$this->SetXY($x + $columnWidths[3], $y);

			$this->Cell($columnWidths[4], $rowHeight, $price, 1, 1, 'R');

			$totalPrice += $event['price'];
		}

		// Total
		if ($res->is_private || $res->is_member || $res->is_volunteer || $res->don || $res->special_red) {
			$this->SetFont('Arial', 'B', 9);
			$this->Cell(160, 6, $this->utf8_to_iso8859_1("Total initial"), 1);
			$this->Cell(25, 6, number_format($totalPrice, 2) . " " . CURRENCY, 1, 1, 'R');
			$this->Ln(5);
			$this->SetFont('Arial', '', 9);
			if (defined_local("ENTITYTYPES")) {
				foreach(ENTITYTYPES as $type) {
					if ($res->{$type['dbkey']}) {
						$this->Cell(160, 10, $this->utf8_to_iso8859_1($type['price_str'] . " (" . ($type['price'] > 0 ? "+" : "") . round(100*$type['price']) . "%)"), 1);
						$this->Cell(25, 10, number_format($type['price']*$totalPrice, 2) . " " . CURRENCY, 1, 1, 'R');
					}
				}
			}
			if ($res->special_red) {
				$this->Cell(160, 10, $this->utf8_to_iso8859_1("Réduction spéciale"), 1);
				$this->Cell(25, 10, number_format(-$special_red, 2) . " " . CURRENCY, 1, 1, 'R');
			}
			if ($res->don) {
				$this->Cell(160, 10, $this->utf8_to_iso8859_1("Don supplémentaire"), 1);
				$this->Cell(25, 10, number_format($res->don, 2) . " " . CURRENCY, 1, 1, 'R');
			}
		}
		$this->SetFont('Arial', 'B', 9);
		$this->Cell(160, 6, $this->utf8_to_iso8859_1("Total (TTC)"), 1);
		$this->Cell(25, 6, number_format($res->price, 2) . " " . CURRENCY, 1, 1, 'R');
		$this->Ln(10);
		$this->SetFont('Arial', '', 12);
		$this->MultiCell(
            185,
            5,
            $this->utf8_to_iso8859_1("Votre demande de réservation " . OF_ROOM . " a bien été reçue. La réservation doit être confirmée par " . lcfirst(THE_ORGANIZATION) . " avant d'être effective. Vous recevrez une confirmation et une facture par email. Si les délais sont courts et que vous ne recevez rien, contactez-nous !")
        );
		
    }

	public function invoicePDF($res) {
		$left = 12;
    	$this->AddPage();
    	$this->SetMargins($left,10);

    	$this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, "FACTURE", 0, 1);
		$this->Ln(5);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        $this->SetFont('Arial', '', 10);

        $this->SetXY($left, $this->GetY());
        $this->MultiCell(30, 6, $this->utf8_to_iso8859_1( 
            "Facture n°:\n" .
            "Date:\n" .
            "Payable au:\n" .
            "Montant dû:\n" .
            "Numéro TVA:"
        ));
        $invoice_date = clone $res->invoice_date; // Don't apply modify +30 days to the original
        $this->SetXY(40, $this->GetY() - 30);
        $this->MultiCell(50, 6, $this->utf8_to_iso8859_1(
            ROOM_SHORT . "-{$res->id}\n" .
            $invoice_date->format('d/m/Y') . "\n" .
            $invoice_date->modify("+" . INVOICE_NORMAL_DUE_DELAY . " days")->format('d/m/Y') . "\n" .
            number_format($res->price, 2, '.', '') . " CHF\n" .
            "Pas enregistré à la TVA"
        ));

        $this->SetXY(130, $this->GetY() - 30);
        $this->MultiCell(80, 5, $this->utf8_to_iso8859_1(
                html_entity_decode($res->entite) . "\n" .
                $res->prenom . " " . $res->nom . "\n" .
                $res->adresse . "\n" .
                $res->npa . " " . $res->localite 
        ));
		$this->SetFont('Arial', 'U', 10);
		$this->SetTextColor(0, 0, 255);
		$this->SetX(130);
		$this->Write(
			5,
			$this->utf8_to_iso8859_1($res->email),
			'mailto:' . $res->email
		);
		$this->SetTextColor(0, 0, 0);
		$this->SetFont('Arial', '', 10);
		$this->Ln();
		$this->SetX(130);
		$this->MultiCell(
			80,
			5,
			$this->utf8_to_iso8859_1($res->telephone)
		);
		
        $this->Ln(5);
        $this->SetLineWidth(0.5);
        $this->Line($left, $this->GetY(), 210-$left, $this->GetY());
        $this->Ln(10);
        
        $this->SetFont('Arial', 'B', 12);
        $this->MultiCell(185, 5, $this->utf8_to_iso8859_1('Réservation ' . OF_ROOM . ' - ' . html_entity_decode($res->nom_evenement)));
		$this->Ln();
        $this->SetFont('Arial', '', 12);
        $this->MultiCell(185, 5, $this->utf8_to_iso8859_1('Description de l\'événement: ' . html_entity_decode($res->description_evenement)));
        $this->Ln();
        

        // Tableau des occurrences
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 6, $this->utf8_to_iso8859_1("Date"), 1);
        $this->Cell(15, 6, $this->utf8_to_iso8859_1("Début"), 1);
        $this->Cell(15, 6, $this->utf8_to_iso8859_1("Fin"), 1);
        $this->Cell(110, 6, $this->utf8_to_iso8859_1("Options"), 1);
        $this->Cell(25, 6, $this->utf8_to_iso8859_1("Prix"), 1, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $totalPrice = 0;
        
        $lineHeight = 5;
		$columnWidths = [20, 15, 15, 110, 25];
        
        foreach ($res->events as $event) {
			$text = $this->utf8_to_iso8859_1($event['text']);
			$price = number_format($event['price'], 2) . " " . CURRENCY;

			// Calcule la hauteur requise pour la cellule texte
			$nbLinesText = $this->NbLines($columnWidths[3], $text);
			$nbLines = max($nbLinesText, 1);

			$rowHeight = max($lineHeight * $nbLines, 7);
			if ($nbLines == 1) $lineHeight = $rowHeight;

			$this->Cell($columnWidths[0], $rowHeight, $event['start_time']->format('d.m.Y'), 1);
			$this->Cell($columnWidths[1], $rowHeight, $event['start_time']->format('H:i'), 1);
			$this->Cell($columnWidths[2], $rowHeight, $event['end_time']->format('H:i'), 1);

			$x = $this->GetX();
			$y = $this->GetY();
			$this->MultiCell($columnWidths[3], $lineHeight, $text, 1);
			$this->SetXY($x + $columnWidths[3], $y);

			$this->Cell($columnWidths[4], $rowHeight, $price, 1, 1, 'R');

			$totalPrice += $event['price'];
		}

		// Total
		if ($res->is_private || $res->is_member || $res->is_volunteer || $res->don || $res->special_red) {
			$this->SetFont('Arial', 'B', 9);
			$this->Cell(160, 6, $this->utf8_to_iso8859_1("Total initial"), 1);
			$this->Cell(25, 6, number_format($totalPrice, 2) . " " . CURRENCY, 1, 1, 'R');
			$this->Ln(5);
			$this->SetFont('Arial', '', 9);
			if (defined_local("ENTITYTYPES")) {
				foreach(ENTITYTYPES as $type) {
					if ($res->{$type['dbkey']}) {
						$this->Cell(160, 10, $this->utf8_to_iso8859_1($type['price_str'] . " (" . ($type['price'] > 0 ? "+" : "") . round(100*$type['price']) . "%)"), 1);
						$this->Cell(25, 10, number_format($type['price']*$totalPrice, 2) . " " . CURRENCY, 1, 1, 'R');
					}
				}
			}
			if ($res->special_red) {
				$this->Cell(160, 8, $this->utf8_to_iso8859_1("Réduction spéciale"), 1);
				$this->Cell(25, 8, number_format($res->special_red, 2) . " " . CURRENCY, 1, 1, 'R');
			}
			if ($res->don) {
				$this->Cell(160, 8, $this->utf8_to_iso8859_1("Don supplémentaire"), 1);
				$this->Cell(25, 8, number_format($res->don, 2) . " " . CURRENCY, 1, 1, 'R');
			}
		}
		$this->SetFont('Arial', 'B', 9);
		$this->Cell(160, 6, $this->utf8_to_iso8859_1("Total (TTC)"), 1);
		$this->Cell(25, 6, number_format($res->price, 2) . " " . CURRENCY, 1, 1, 'R');
		$this->Ln(10);
		$this->SetFont('Arial', '', 12);
		$invoice_due_str = "";
		if (defined_local("INVOICE_DUE_DAYS_BEFORE_RES") && INVOICE_DUE_DAYS_BEFORE_RES >= 0) {
			if (INVOICE_DUE_DAYS_BEFORE_RES == 0) $str_days = "le jour de";
			elseif (INVOICE_DUE_DAYS_BEFORE_RES == 1) $str_days = "un jour avant";
			else $str_days = INVOICE_DUE_DAYS_BEFORE_RES . " jours avant";
			$invoice_due_str = "La facture doit en principe être réglée au plus tard " . $str_days . " la première date de location. " . ucfirst(THE_ORGANIZATION) . " se réserve la possibilité d'annuler la location en cas de non-paiement. ";	
		}
		$this->MultiCell(
            185,
            5,
            $this->utf8_to_iso8859_1("Merci pour votre réservation. " . $invoice_due_str . "Pour toute question, n'hésitez pas à nous contacter.")
        );
        $this->AddPage();
		$this->setSourceFile(__DIR__ . '/../' . BANK_QR_PDF);
		$tplId = $this->importPage(1);
		$this->useTemplate($tplId, 0, 0, 210);		
    }
    
    function NbLines($width, $text) {
		$cw = $this->CurrentFont['cw'];
		if ($width == 0) {
			$width = $this->w - $this->rMargin - $this->x;
		}
		$words = explode(' ', $text);
		$lines = 1;
		$currentWidth = 0;

		foreach ($words as $word) {
			$wordWidth = 0;
			for ($i = 0; $i < strlen($word); $i++) {
				$wordWidth += $cw[$word[$i]];
			}
			$wordWidth += $cw[' ']; // Espace après le mot
			if ($currentWidth + $wordWidth > $width * 1000 / $this->FontSize) {
				$lines++;
				$currentWidth = $wordWidth;
			} else {
				$currentWidth += $wordWidth;
			}
		}
		return $lines;
	}

    // Fonction pour convertir UTF-8 en ISO-8859-1
	public function utf8_to_iso8859_1($text) {
    	return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
	}
}
?>