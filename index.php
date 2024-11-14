<?php
// Ścieżka do pliku CSV z danymi Messiera
$csvFile = 'dso.csv';

$dsoFullNames = [
    'I' => 'IC',
    'N' => 'NGC',
    'M' => 'MESSIER',
    'C' => 'CALDWELL'
];

function generateMap($filteredObjects, $mapObjectsCount, $mapSize, $azimuthStart, $azimuthEnd, $altitudeStart, $altitudeEnd, $longitude, $latitude, $date, $time, $timezone) {
    list($width, $height) = explode('x', $mapSize);
    
    // Ustal margines
    $margin = 25;
    
    // Zaktualizowana szerokość i wysokość obszaru rysowania z uwzględnieniem marginesu
    $drawingWidth = $width - 2 * $margin;
    $drawingHeight = $height - 2 * $margin;
    
    // Inicjalizacja SVG
    $svg = "<svg width='$width' height='$height' xmlns='http://www.w3.org/2000/svg'>";
    
    // Tło
    $svg .= "<rect width='100%' height='100%' fill='#f0f0f0'/>";
    
    // Zaokrąglij wartości początkowe i końcowe do wielokrotności 10
	$azimuthStart = floor($azimuthStart / 10) * 10;
	$azimuthEnd = ceil($azimuthEnd / 10) * 10;
	$altitudeStart = floor($altitudeStart / 10) * 10;
	$altitudeEnd = ceil($altitudeEnd / 10) * 10;

    // Rysowanie siatki
    $azimuthStep = 10;
    $altitudeStep = 10;
    
    for ($az = $azimuthStart; $az <= $azimuthEnd; $az += $azimuthStep) {
        // Przesunięcie o margines dla osi x
        $x = ($az - $azimuthStart) / ($azimuthEnd - $azimuthStart) * $drawingWidth + $margin;
        $svg .= "<line x1='$x' y1='$margin' x2='$x' y2='" . ($height - $margin) . "' stroke='#ccc' stroke-width='1'/>";
        $svg .= "<text x='$x' y='" . ($height - 5) . "' font-size='10' text-anchor='middle'>$az °</text>";
    }
    
    for ($alt = $altitudeStart; $alt <= $altitudeEnd; $alt += $altitudeStep) {
        // Przesunięcie o margines dla osi y
        $y = $height - ($alt - $altitudeStart) / ($altitudeEnd - $altitudeStart) * $drawingHeight - $margin;
        $svg .= "<line x1='$margin' y1='$y' x2='" . ($width - $margin) . "' y2='$y' stroke='#ccc' stroke-width='1'/>";
        $svg .= "<text x='5' y='$y' font-size='10' text-anchor='start'>$alt °</text>";
    }
    
    // Rysowanie obiektów
    $objectCount = 0;
    foreach ($filteredObjects as $object) {
        if ($objectCount >= $mapObjectsCount) break;
        
        $ra_stopnie = 0;
		$ra_stopnie += is_numeric($object['RH']) ? floatval($object['RH']) * 15 : 0;
		$ra_stopnie += is_numeric($object['RM']) ? floatval($object['RM']) / 60 * 15 : 0;
		$ra_stopnie += is_numeric($object['RS']) ? floatval($object['RS']) / 3600 * 15 : 0;

		$dec_stopnie = 0;
		$dec_stopnie += is_numeric($object['DG']) ? floatval($object['DG']) : 0;
		$dec_stopnie += is_numeric($object['DM']) ? floatval($object['DM']) / 60 : 0;
		$dec_stopnie += is_numeric($object['DS']) ? floatval($object['DS']) / 3600 : 0;
        
		if ($object['V'] == '-') $dec_stopnie = -$dec_stopnie; // Obsługa południowych deklinacji
			
        $szer_dziesietna = stopnieMinutySekundyNaStopnie($latitude);
        $dlug_dziesietna = stopnieMinutySekundyNaStopnie($longitude);
        
		
		
			if (preg_match('/([+-])(\d{2}):(\d{2})/', $timezone, $matches)) {
				$znak = $matches[1];
				$godziny = intval($matches[2]);
				$minuty = intval($matches[3]);
				
				// Oblicz przesunięcie w sekundach
				$sekundy_przesuniecia = ($godziny * 3600) + ($minuty * 60);
				if ($znak === '-') {
					$sekundy_przesuniecia = -$sekundy_przesuniecia;
				}

				// Dodaj przesunięcie do czasu
				$czas_z_timezone = date('H:i', strtotime($time) - $sekundy_przesuniecia);
				
			} else {
				$czas_z_timezone = $time;  // Jeśli nie ma strefy czasowej, użyj oryginalnego czasu
			}
				
	
			
			
			$wspolrzedne = obliczAzymutWysokosc($ra_stopnie, $dec_stopnie, $szer_dziesietna, $dlug_dziesietna, $date, $czas_z_timezone);
			$azimuth = $wspolrzedne['azymut'];
        $altitude = $wspolrzedne['wysokosc'];
		
        if ($azimuth >= $azimuthStart && $azimuth <= $azimuthEnd && 
            $altitude >= $altitudeStart && $altitude <= $altitudeEnd) {
            
            // Przesunięcie o margines dla pozycji obiektów
            $x = ($azimuth - $azimuthStart) / ($azimuthEnd - $azimuthStart) * $drawingWidth + $margin;
            $y = $height - ($altitude - $altitudeStart) / ($altitudeEnd - $altitudeStart) * $drawingHeight - $margin;
            
            $sizeX = floatval(str_replace(',', '.', $object['Size X'])) * 5; // Skalowanie rozmiaru
            $sizeY = floatval(str_replace(',', '.', $object['Size Y'])) * 5;
            
			$svg .= "<ellipse cx='$x' cy='$y' rx='" . ($sizeX / 60) . "' ry='" . ($sizeY / 60) . "' fill='blue' opacity='0.5'/>";
            $svg .= "<text x='$x' y='" . ($y + $sizeY / 60 + 10) . "' font-size='10' text-anchor='middle'>{$object['DSO']}{$object['NR']}</text>";
            
            $objectCount++;
        }
    }
    
    $svg .= "</svg>";
    return $svg;
}


// Funkcja obliczająca lokalny czas gwiazdowy (LST)
function obliczLST($dlugosc, $data, $czas) {
    // Obliczanie UTC z podanej daty i godziny
    $timestamp = strtotime("$data $czas UTC");
    
    // Obliczanie Juliańskiej Daty dla UTC
    $jd = ($timestamp / 86400.0) + 2440587.5;
    
    // Obliczanie GMST (Greenwich Mean Sidereal Time)
    $J2000 = 2451545.0;
    $T = ($jd - $J2000) / 36525.0;
    $gmst = 280.46061837 + 360.98564736629 * ($jd - $J2000) + 0.000387933 * $T * $T - $T * $T * $T / 38710000;
    
    // Normalizacja GMST do zakresu 0-360 stopni
    $gmst = fmod($gmst, 360.0);
    if ($gmst < 0) $gmst += 360.0;
    
    // Dodanie długości geograficznej do GMST, aby otrzymać LST
    $lst = $gmst + $dlugosc;
    
    return fmod($lst + 360.0, 360.0);  // Normalizacja do zakresu 0-360 stopni
}

// Funkcja obliczająca azymut i wysokość z uwzględnieniem lokalnego czasu gwiazdowego
function obliczAzymutWysokosc($ra, $dec, $szer, $dlug, $data, $czas) {
   
   $lst = obliczLST($dlug, $data, $czas);
    
    // Obliczenie kąta godzinowego (Hour Angle)
    $ha = $lst - $ra;
    if ($ha < 0) $ha += 360;

    // Konwersja szerokości geograficznej, deklinacji i kąta godzinowego na radiany
    $szer_rad = deg2rad($szer);
    $dec_rad = deg2rad($dec);
    $ha_rad = deg2rad($ha);
    
    // Obliczanie wysokości (Altitude)
    $sin_wys = sin($dec_rad) * sin($szer_rad) + cos($dec_rad) * cos($szer_rad) * cos($ha_rad);
    $wys = rad2deg(asin($sin_wys));
    
    // Obliczanie azymutu (Azimuth)
    $cos_az = (sin($dec_rad) - sin($szer_rad) * $sin_wys) / (cos($szer_rad) * cos(deg2rad($wys)));
    $az = rad2deg(acos($cos_az));
    
    // Użycie sin(HA) do ustalenia poprawnego kwadrantu azymutu
    if (sin($ha_rad) > 0) {
        $az = 360 - $az;
    }
    
    return array('azymut' => round($az, 2), 'wysokosc' => round($wys, 2));
}

// Funkcja konwertująca współrzędne na stopnie dziesiętne
function stopnieMinutySekundyNaStopnie($dms) {
    $dms = str_replace(',', '.', $dms); // Zamiana przecinka na kropkę
    $czesci = explode('°', $dms);
    if (count($czesci) != 2) return floatval($dms); // Jeśli format jest niepoprawny, zwróć oryginalną wartość
    
    $stopnie = floatval($czesci[0]);
    $czesci = explode("'", $czesci[1]);
    
    if (count($czesci) >= 2) {
        $minuty = floatval($czesci[0]);
        $sekundy = floatval(str_replace('"', '', $czesci[1]));
    } else {
        $minuty = floatval($czesci[0]);
        $sekundy = 0;
    }
    
    $znak = ($stopnie < 0 || strpos($dms, '-') === 0) ? -1 : 1;
    $stopnie_dziesietne = abs($stopnie) + ($minuty / 60) + ($sekundy / 3600);
    return $znak * $stopnie_dziesietne;
}


function obliczLST1($dlugosc, $data, $czas) {
    $timestamp = strtotime("$data $czas");
    $J2000 = 2451545.0;
    $jd = ($timestamp / 86400) + 2440587.5;
    $T = ($jd - $J2000) / 36525.0;
    $gmst = 280.46061837 + 360.98564736629 * ($jd - 2451545.0) + 0.000387933 * $T * $T - $T * $T * $T / 38710000;
    $gmst = fmod($gmst, 360.0);
    $lst = $gmst + $dlugosc;
    return fmod($lst, 360.0);
}

function obliczAzymutWysokosc1($ra, $dec, $szer, $dlug, $data, $czas) {
    $timestamp = strtotime("$data $czas");
    $lst = obliczLST($dlug, $data, $czas);
    $ha = $lst - $ra;
    if ($ha < 0) $ha += 360;

    $szer_rad = deg2rad($szer);
    $dec_rad = deg2rad($dec);
    $ha_rad = deg2rad($ha);
    
    $sin_wys = sin($dec_rad) * sin($szer_rad) + cos($dec_rad) * cos($szer_rad) * cos($ha_rad);
    $wys = rad2deg(asin($sin_wys));
    
    $cos_az = (sin($dec_rad) - sin($szer_rad) * $sin_wys) / (cos($szer_rad) * cos(deg2rad($wys)));
    $az = rad2deg(acos($cos_az));
    if (sin($ha_rad) > 0) $az = 360 - $az;
    
    return array('azymut' => $az, 'wysokosc' => $wys);
}

function stopnieMinutySekundyNaStopnie1($dms) {
    $dms = str_replace(',', '.', $dms); // Zamiana przecinka na kropkę
    $czesci = explode('°', $dms);
    if (count($czesci) != 2) return floatval($dms); // Jeśli format jest niepoprawny, zwróć oryginalną wartość
    
    $stopnie = floatval($czesci[0]);
    $czesci = explode("'", $czesci[1]);
    
    if (count($czesci) >= 2) {
        $minuty = floatval($czesci[0]);
        $sekundy = floatval(str_replace('"', '', $czesci[1]));
    } else {
        $minuty = floatval($czesci[0]);
        $sekundy = 0;
    }
    
    $znak = ($stopnie < 0 || strpos($dms, '-') === 0) ? -1 : 1;
    $stopnie_dziesietne = abs($stopnie) + ($minuty / 60) + ($sekundy / 3600);
    return $znak * $stopnie_dziesietne;
}

// Funkcja, która czyta plik CSV i zwraca dane dla wszystkich obiektów Messiera
function getAllMessierData() {
    global $csvFile;
    
    $messierObjects = [];
    
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // Odczytujemy nagłówki pliku CSV
        $header = fgetcsv($handle, 1000, ";");
        
        // Odczytujemy wszystkie dane i zapisujemy je do tablicy
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $messierObjects[] = array_combine($header, $data);
        }
        fclose($handle);
    }
    
    return $messierObjects;
}

function sortMessierObjects($objects, $sortBy) {
    usort($objects, function($a, $b) use ($sortBy) {
        // Zamieniamy przecinki na kropki, aby poprawnie przekształcać liczby
        $aValue = str_replace(',', '.', trim($a[$sortBy]));
        $bValue = str_replace(',', '.', trim($b[$sortBy]));
        
        // Sprawdzamy, czy wartości są liczbami
        if (is_numeric($aValue) && is_numeric($bValue)) {
            // Jeśli oba są liczbami, sortujemy numerycznie
            return floatval($aValue) <=> floatval($bValue);
        } else {
            // Sortowanie naturalne dla wartości tekstowych
            return strnatcmp($aValue, $bValue);
        }
    });
    return $objects;
}

$mapObjectsCount = isset($_GET['map_objects_count']) ? intval($_GET['map_objects_count']) : 20;
$mapSize = isset($_GET['map_size']) ? $_GET['map_size'] : '1024x768';

// Pobieramy wszystkie dane obiektów Messiera
$messierObjects = getAllMessierData();

// Pobieranie unikalnych wartości DSO
$uniqueDSO = array_unique(array_map(function($object) {
    return $object['DSO'];
}, $messierObjects));
sort($uniqueDSO);  // Sortowanie alfabetyczne

// Pobieranie wybranych wartości DSO z formularza
$selectedDSO = isset($_GET['dso']) ? $_GET['dso'] : [];

// Pobieranie unikalnych typów obiektów
$uniqueTypes = array_unique(array_map(function($object) {
    return $object['Type'];
}, $messierObjects));
sort($uniqueTypes);  // Sortowanie alfabetyczne

// Pobieranie wybranych typów z formularza
$selectedTypes = isset($_GET['types']) ? $_GET['types'] : [];

// Znalezienie minimalnych i maksymalnych wartości dla parametrów
$minMagDefault = min(array_map(function($object) { return floatval(str_replace(',', '.', $object['Mag.'])); }, $messierObjects));
$maxMagDefault = max(array_map(function($object) { return floatval(str_replace(',', '.', $object['Mag.'])); }, $messierObjects));
$minSizeXDefault = min(array_map(function($object) { return floatval(str_replace(',', '.', $object['Size X'])); }, $messierObjects));
$maxSizeXDefault = max(array_map(function($object) { return floatval(str_replace(',', '.', $object['Size X'])); }, $messierObjects));
$minSizeYDefault = min(array_map(function($object) { return floatval(str_replace(',', '.', $object['Size Y'])); }, $messierObjects));
$maxSizeYDefault = max(array_map(function($object) { return floatval(str_replace(',', '.', $object['Size Y'])); }, $messierObjects));

// Pobieranie danych z formularza (filtrów)
$minMag = isset($_GET['min_mag']) ? floatval($_GET['min_mag']) : $minMagDefault;
$maxMag = isset($_GET['max_mag']) ? floatval($_GET['max_mag']) : $maxMagDefault;
$minSizeX = isset($_GET['min_size_x']) ? floatval($_GET['min_size_x']) : $minSizeXDefault;
$maxSizeX = isset($_GET['max_size_x']) ? floatval($_GET['max_size_x']) : $maxSizeXDefault;
$minSizeY = isset($_GET['min_size_y']) ? floatval($_GET['min_size_y']) : $minSizeYDefault;
$maxSizeY = isset($_GET['max_size_y']) ? floatval($_GET['max_size_y']) : $maxSizeYDefault;

// Pobieranie parametru sortowania
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'DSO';

// Pobieranie danych o lokalizacji i czasie
$location = isset($_GET['location']) ? $_GET['location'] : '';
$latitude = isset($_GET['latitude']) ? $_GET['latitude'] : '';
$longitude = isset($_GET['longitude']) ? $_GET['longitude'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$time = isset($_GET['time']) ? $_GET['time'] : '';
$timezone = isset($_GET['timezone']) ? $_GET['timezone'] : '';


$visibility = isset($_GET['visibility']) ? $_GET['visibility'] : 'all';
$azimuthStart = isset($_GET['azimuth_start']) ? intval($_GET['azimuth_start']) : 20;
$azimuthEnd = isset($_GET['azimuth_end']) ? intval($_GET['azimuth_end']) : 300;

$altitudeStart = isset($_GET['altitude_start']) ? intval($_GET['altitude_start']) : 25;
$altitudeEnd = isset($_GET['altitude_end']) ? intval($_GET['altitude_end']) : 75;

$showImages = isset($_GET['zdjecia']) ? $_GET['zdjecia'] : '0';


// Filtrujemy dane na podstawie wprowadzonych parametrów i wybranych typów
//$filteredMessierObjects = array_filter($messierObjects, function($object) use ($minMag, $maxMag, $minSizeX, $maxSizeX, $minSizeY, $maxSizeY, $selectedTypes, $selectedDSO) {
  $filteredMessierObjects = array_filter($messierObjects, function($object) use ($minMag, $maxMag, $minSizeX, $maxSizeX, $minSizeY, $maxSizeY, $selectedTypes, $selectedDSO, $visibility, $azimuthStart, $azimuthEnd, $altitudeStart, $altitudeEnd, $latitude, $longitude, $date, $time, $timezone) {

    $mag = floatval(str_replace(',', '.', $object['Mag.']));
    $sizeX = floatval(str_replace(',', '.', $object['Size X']));
    $sizeY = floatval(str_replace(',', '.', $object['Size Y']));
    
    // Sprawdzenie, czy obiekt należy do wybranych typów
    $typeCheck = empty($selectedTypes) || in_array($object['Type'], $selectedTypes);
    $dsoCheck = empty($selectedDSO) || in_array($object['DSO'], $selectedDSO);
       // Dodatkowa logika dla widzialności
    if ($visibility === 'visible' || $visibility === 'in_area') {
        if ($latitude != '' && $longitude != '' && $date != '' && $time != '') {
			$ra_stopnie = 0;
			$ra_stopnie += is_numeric($object['RH']) ? floatval($object['RH']) * 15 : 0;
			$ra_stopnie += is_numeric($object['RM']) ? floatval($object['RM']) / 60 * 15 : 0;
			$ra_stopnie += is_numeric($object['RS']) ? floatval($object['RS']) / 3600 * 15 : 0;
			
			//echo "RH: " . $object['RH'] . ", RM: " . $object['RM'] . ", RS: " . $object['RS'] . "<br>";
			//echo "RA stopnie: " . $ra_stopnie . "<br>";

			$dec_stopnie = 0;
			$dec_stopnie += is_numeric($object['DG']) ? floatval($object['DG']) : 0;
			$dec_stopnie += is_numeric($object['DM']) ? floatval($object['DM']) / 60 : 0;
			$dec_stopnie += is_numeric($object['DS']) ? floatval($object['DS']) / 3600 : 0;
            if ($object['V'] == '-') $dec_stopnie = -$dec_stopnie; // Obsługa południowych deklinacji
			
		
	
            $szer_dziesietna = stopnieMinutySekundyNaStopnie($latitude);
            $dlug_dziesietna = stopnieMinutySekundyNaStopnie($longitude);
            
            if (preg_match('/([+-])(\d{2}):(\d{2})/', $timezone, $matches)) {
				$znak = $matches[1];
				$godziny = intval($matches[2]);
				$minuty = intval($matches[3]);
				
				// Oblicz przesunięcie w sekundach
				$sekundy_przesuniecia = ($godziny * 3600) + ($minuty * 60);
				if ($znak === '-') {
					$sekundy_przesuniecia = -$sekundy_przesuniecia;
				}

				// Dodaj przesunięcie do czasu
				$czas_z_timezone = date('H:i', strtotime($time) - $sekundy_przesuniecia);
				
			} else {
				$czas_z_timezone = $time;  // Jeśli nie ma strefy czasowej, użyj oryginalnego czasu
			}
				
	
			
			
			$wspolrzedne = obliczAzymutWysokosc($ra_stopnie, $dec_stopnie, $szer_dziesietna, $dlug_dziesietna, $date, $czas_z_timezone);
			$azymut = $wspolrzedne['azymut'];
            $wysokosc = $wspolrzedne['wysokosc'];
            
            if ($visibility === 'visible' && $wysokosc <= 0) {
                return false;
            }
            
            if ($visibility === 'in_area') {
                $azymutInRange = ($azimuthStart <= $azimuthEnd) 
                    ? ($azymut >= $azimuthStart && $azymut <= $azimuthEnd)
                    : ($azymut >= $azimuthStart || $azymut <= $azimuthEnd);
                

                if (!$azymutInRange || $wysokosc < $altitudeStart || $wysokosc > $altitudeEnd) {
                    return false;
                }
            }
        } else {
            // Jeśli brakuje danych do obliczeń, nie pokazujemy obiektu
            return false;
        }
	}
    return ($mag >= $minMag && $mag <= $maxMag) &&
           ($sizeX >= $minSizeX && $sizeX <= $maxSizeX) &&
           ($sizeY >= $minSizeY && $sizeY <= $maxSizeY) &&
           $typeCheck &&
           $dsoCheck;
});

// Sortowanie przefiltrowanych obiektów
$filteredMessierObjects = sortMessierObjects($filteredMessierObjects, $sortBy);

session_start();


?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
	
	
    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Katalog DSO</title>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.css' rel='stylesheet'>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/14.6.3/nouislider.min.js'></script>
    <style>
		.slider-container {
			display: flex;
			align-items: center;
			margin: 10px 0;
		}
		.slider-label {
			flex: 1;
			margin-right: 30px;
		}
		.slider {
			flex: 2;
			margin-right: 85px; 
		}
		.checkbox-container {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin-top: 10px;
		}
		.checkbox-container label {
			display: flex;
			align-items: center;
			padding: 10px;
			background-color: #f4f4f4;
			border-radius: 5px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			cursor: pointer;
		}
		.checkbox-container input[type="checkbox"] {
			margin-right: 10px;
		}
		.noUi-tooltip {
			background-color: #333;
			color: #fff;
			padding: 12px 12px;
			border-radius: 3px;
			position: absolute;
			top: 0px;
			white-space: nowrap;
			font-size: 14px;
			line-height: 0.2;
			z-index: 1;
		}
		.noUi-handle[data-handle="0"] .noUi-tooltip {
			transform: translateX(-130%);
		}
		.noUi-handle[data-handle="1"] .noUi-tooltip {
			transform: translateX(30%);
		}
		.radio-container {
			display: flex;
			justify-content: space-between;
			margin-bottom: 20px;
			flex-wrap: nowrap;
		}
		.radio-container label {
			display: flex;
			align-items: center;
			padding: 10px;
			background-color: #f4f4f4;
			border-radius: 5px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			cursor: pointer;
			margin-right: 10px;
			white-space: nowrap;
		}
		.radio-container label:last-child {
			margin-right: 0;
		}
		.filter-sort-container {
			display: flex;
			justify-content: space-between;
			margin-bottom: 20px;
		}
		.filter-section, .sort-section {
			width: 48%;
		}
		.input-grid {
			display: flex;
			flex-direction: column;
			gap: 10px;
		}
		.input-row {
			display: flex;
			gap: 10px;
			align-items: center;
		}
		.input-row label {
			flex: 1;
			display: flex;
			align-items: center;
			background-color: #f4f4f4;
			padding: 5px;
			border-radius: 5px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		}
		.input-row input {
			flex: 1;
			padding: 5px;
			border: 1px solid #ccc;
			border-radius: 3px;
		}
		.input-row button {
			padding: 5px 10px;
			background-color: #007bff;
			color: white;
			border: none;
			border-radius: 3px;
			cursor: pointer;
		}
		.input-row button:hover {
			background-color: #0056b3;
		}
		#azimuth-canvas {
			background-color: #fff;
			border-radius: 50%;
			box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
			margin: 20px auto;
			display: block;
		}
		#horizon-widget {
			display: flex;
			flex-direction: column;
			margin-right: 85px; 
		}
		#altitude-widget {
			display: flex;
			flex-direction: column;
			margin-right: 85px; 
		}
		#altitude-widget input[type="range"] {
			margin: 10px 0;
		}
		
		.value-label {
			
			padding: 5px 10px;
			border-radius: 5px;
			color: white;
			font-weight: bold;
		}
		#label-1a {
			top: 10px;
			left: 10px;
			background-color: #ff5722;
		}
		#label-1b {
			top: 10px;
			left: 100px;
			background-color: #03a9f4;
		}		
		#label-a {
			top: 10px;
			left: 10px;
			background-color: #333;
			color: #fff;
			
			border-radius: 3px;
		}
		#label-b {
			top: 10px;
			left: 100px;
			background-color: #333;
			color: #fff;
			
			border-radius: 3px;
		}
    </style>
</head>
<body>


  
	
	
	
    <h1>Katalog Deep Sky Objects</h1>
    <?php
	
		if (isset($_GET['generate_map']) && $_GET['generate_map'] == '1') {
		if (isset($_GET['visibility']) && $_GET['visibility'] == 'in_area') {
			$svg = generateMap($filteredMessierObjects, $mapObjectsCount, $mapSize, $azimuthStart, $azimuthEnd, $altitudeStart, $altitudeEnd, $longitude, $latitude, $date, $time, $timezone);
		}
		else {
			$svg = generateMap($filteredMessierObjects, $mapObjectsCount, $mapSize, 0, 360, 0, 90, $longitude, $latitude, $date, $time, $timezone);
		
		}
		//header("Content-Type: image/svg+xml");
		echo $svg;
		exit;
	}
	
	
	// Formularz filtrów z suwakami noUiSlider, checkboxami dla typów i przyciskami radio do sortowania
	echo "<form id='filterForm' method='GET' action=''>";

	// Suwak dla jasności (Mag.)
	echo "<div class='slider-container'>";
	echo "<label class='slider-label'>Jasność (Mag.): Od <span id='min_mag_val'>$minMag</span> do <span id='max_mag_val'>$maxMag</span></label>";
	echo "<div id='magSlider' class='slider'></div>";
	echo "<input type='hidden' name='min_mag' id='min_mag' value='$minMag'>";
	echo "<input type='hidden' name='max_mag' id='max_mag' value='$maxMag'>";
	echo "</div>"; // Suwak dla jasności

	// Suwak dla rozmiaru X
	echo "<div class='slider-container'>";
	echo "<label class='slider-label'>Rozmiar X: Od <span id='min_size_x_val'>$minSizeX</span> do <span id='max_size_x_val'>$maxSizeX</span></label>";
	echo "<div id='sizeXSlider' class='slider'></div>";
	echo "<input type='hidden' name='min_size_x' id='min_size_x' value='$minSizeX'>";
	echo "<input type='hidden' name='max_size_x' id='max_size_x' value='$maxSizeX'>";
	echo "</div>"; // Suwak dla rozmiaru X

	// Suwak dla rozmiaru Y
	echo "<div class='slider-container'>";
	echo "<label class='slider-label'>Rozmiar Y: Od <span id='min_size_y_val'>$minSizeY</span> do <span id='max_size_y_val'>$maxSizeY</span></label>";
	echo "<div id='sizeYSlider' class='slider'></div>";
	echo "<input type='hidden' name='min_size_y' id='min_size_y' value='$minSizeY'>";
	echo "<input type='hidden' name='max_size_y' id='max_size_y' value='$maxSizeY'>";
	echo "</div>"; // Suwak dla rozmiaru Y

	// Lokalizacja i czas
	echo "<div class='filter-sort-container'>"; // Początek głównego kontenera
	echo "<div class='filter-section'>";
	echo "<h3>Lokalizacja i czas:</h3>";
	echo "<div class='input-grid'>";

	// Pierwszy wiersz - lokalizacja
	echo "<div class='input-row'>";
	echo "<label><input type='text' id='location' name='location' placeholder='Miejscowość' value='$location'></label>";
	echo "<label><input type='text' id='latitude' name='latitude' placeholder='Szerokość geograficzna' value='$latitude'></label>";
	echo "<label><input type='text' id='longitude' name='longitude' placeholder='Długość geograficzna' value='$longitude'></label>";
	echo "<button type='button' id='searchLocation'>Szukaj</button>";
	echo "</div>"; // Koniec pierwszego wiersza

	// Drugi wiersz - czas
	echo "<div class='input-row'>";
	echo "<label><input type='date' id='date' name='date' value='$date'></label>";
	echo "<label><input type='time' id='time' name='time' value='$time'></label>";
	echo "<label><input type='text' id='timezone' name='timezone' value='$timezone'></label>";
	echo "<button type='button' id='setNow'>Teraz</button>";
	echo "</div>"; // Koniec drugiego wiersza

	// Lewa strona - filtrowanie po DSO
	echo "<div class='filter-section'>";
	echo "<h3>Filtruj po DSO:</h3>";
	echo "<div class='checkbox-container'>";
	foreach ($uniqueDSO as $dso) {
		$checked = in_array($dso, $selectedDSO) ? "checked" : "";
		$fullName = isset($dsoFullNames[$dso]) ? $dsoFullNames[$dso] : $dso;
		echo "<label><input type='checkbox' name='dso[]' value='$dso' $checked> $fullName</label>";
	}
	echo "</div>"; // Koniec checkbox-container

	echo "<h3>Sortuj według:</h3>";
	echo "<div class='radio-container'>";
	echo "<label><input type='radio' name='sort' value='DSO' " . ($sortBy == 'DSO' ? 'checked' : '') . "> Nazwa</label>";
	echo "<label><input type='radio' name='sort' value='Mag.' " . ($sortBy == 'Mag.' ? 'checked' : '') . "> Jasność</label>";
	echo "<label><input type='radio' name='sort' value='Size X' " . ($sortBy == 'Size X' ? 'checked' : '') . "> Rozmiar X</label>";
	echo "<label><input type='radio' name='sort' value='Size Y' " . ($sortBy == 'Size Y' ? 'checked' : '') . "> Rozmiar Y</label>";
	echo "</div>"; // Koniec radio-container

	echo "<label><input type='checkbox' name='zdjecia' value='1' " . ($showImages == '1' ? 'checked' : '') . "> Pokaż zdjęcia</label>";



?>
	<h3>Generowanie mapy obiektów:</h3>
<div class="input-row">
    <label>Liczba obiektów:
        <select name="map_objects_count">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="40">40</option>
            <option value="50">50</option>
            <option value="60">60</option>
            <option value="70">70</option>
            <option value="80">80</option>
            <option value="90">90</option>
            <option value="100">100</option>
        </select>
    </label>
    <label>Rozmiar mapy:
        <select name="map_size">
            <option value="640x480">640x480</option>
            <option value="1024x768">1024x768</option>
            <option value="1920x1080">1920x1080</option>
        </select>
    </label>
    <button type="button" id="generateMap">Generuj mapę</button>
</div>
<?php
	echo "</div>"; // Koniec filter-section


	echo "</div>"; // Koniec input-grid
	echo "</div>"; // Koniec filter-section


	// Prawa strona - dodatkowe opcje
	echo "<div class='sort-section'>";
	echo "<h3>Dodatkowe opcje:</h3>";

	// Widoczność
	echo "<div>";
	echo "<h4>Widzialność:</h4>";
	echo "<label><input type='radio' name='visibility' value='all' " . (!isset($_GET['visibility']) || $_GET['visibility'] == 'all' ? 'checked' : '') . "> Wszystkie</label>";
	echo "<label><input type='radio' name='visibility' value='visible' " . (isset($_GET['visibility']) && $_GET['visibility'] == 'visible' ? 'checked' : '') . "> Widoczne</label>";
	echo "<label><input type='radio' name='visibility' value='in_area' " . (isset($_GET['visibility']) && $_GET['visibility'] == 'in_area' ? 'checked' : '') . "> W obszarze</label>";
	echo "</div>"; // Koniec widoczności

	// Zakres azymutu
	echo "<div id='horizon-widget' style='display: " . (isset($_GET['visibility']) && $_GET['visibility'] == 'in_area' ? 'block' : 'none') . ";'>";
	echo "<h4>Zakres azymutu:</h4>";
	?>
	<label id="label-a" class="value-label"></label><label> - </label>
	<label id="label-b" class="value-label"></label>
	<?php
	echo "<canvas id='azimuth-canvas' width='200' height='200'></canvas>";
	echo "<input type='hidden' name='azimuth_start' id='azimuth_start' value='" . (isset($_GET['azimuth_start']) ? $_GET['azimuth_start'] : '100') . "'>";
	echo "<input type='hidden' name='azimuth_end' id='azimuth_end' value='" . (isset($_GET['azimuth_end']) ? $_GET['azimuth_end'] : '200') . "'>";
	echo "</div>"; // Koniec horizon-widget

	// Zakres wysokości
	echo "<div id='altitude-widget' style='display: " . (isset($_GET['visibility']) && $_GET['visibility'] == 'in_area' ? 'block' : 'none') . ";'>";
	echo "<h4>Zakres wysokości:</h4>";
	echo "<div id='altitude-slider'></div>";
	echo "<input type='hidden' name='altitude_start' id='altitude_start' value='" . (isset($_GET['altitude_start']) ? $_GET['altitude_start'] : '10') . "'>";
	echo "<input type='hidden' name='altitude_end' id='altitude_end' value='" . (isset($_GET['altitude_end']) ? $_GET['altitude_end'] : '80') . "'>";
	echo "</div>"; // Koniec altitude-widget




	echo "</div>"; // Koniec sort-section
	echo "</div>"; // Koniec filter-sort-container



    // Checkboxy dla typów obiektów
    echo "<h3>Typ obiektu:</h3>";
    echo "<div class='checkbox-container'>";
    foreach ($uniqueTypes as $type) {
        $checked = in_array($type, $selectedTypes) ? "checked" : "";
        echo "<label><input type='checkbox' name='types[]' value='$type' $checked> $type</label>";
    }
    echo "</div>";

    echo "</form>";
    ?>
	

    <?php
	

    // Wyświetlanie wyników
    echo "<div style='display: flex; flex-wrap: wrap;'>";

    foreach ($filteredMessierObjects as $object) {
        if($object['DSO']=="M") $mNumber="MESSIER ".$object['NR'];
        if($object['DSO']=="N") $mNumber="NGC ".$object['NR'];
        if($object['DSO']=="I") $mNumber="IC ".$object['NR'];
        if($object['DSO']=="C") $mNumber="CALDWELL ".$object['NR'];

        $commonName = $object['Common Name'];
        $type = $object['Type'];
        $mag = $object['Mag.'];
        $sizeX = $object['Size X'];
        $sizeY = $object['Size Y'];
        $ra = sprintf('%02d:%02d:%04.1f', $object['RH'], $object['RM'], $object['RS']);
        $dec=$object['V'].$object['DG']."&deg"." ".$object['DM']."' ".$object['DS']."\"";

        // Wyświetlanie kafelki z obiektem Messiera
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px; width: 250px;'>";
        echo "<h2>$mNumber</h2>";
        echo "<p style='margin: 2px 0;'><strong>Nazwa:</strong> $commonName</p>";
        echo "<p style='margin: 2px 0;'><strong>Typ:</strong> $type</p>";
        echo "<p style='margin: 2px 0;'><strong>Jasność:</strong> $mag</p>";
        echo "<p style='margin: 2px 0;'><strong>Rozmiar:</strong> $sizeX x $sizeY</p>";
        echo "<p style='margin: 2px 0;'><strong>RA:</strong> $ra</p>";
        echo "<p style='margin: 2px 0;'><strong>Dec:</strong> $dec</p>";
        
		if ($latitude != '' && $longitude != '' && $date != '' && $time != '') {
			$ra_stopnie = 0;
			$ra_stopnie += is_numeric($object['RH']) ? floatval($object['RH']) * 15 : 0;
			$ra_stopnie += is_numeric($object['RM']) ? floatval($object['RM']) / 60 * 15 : 0;
			$ra_stopnie += is_numeric($object['RS']) ? floatval($object['RS']) / 3600 * 15 : 0;
		
			$dec_stopnie = 0;
			$dec_stopnie += is_numeric($object['DG']) ? floatval($object['DG']) : 0;
			$dec_stopnie += is_numeric($object['DM']) ? floatval($object['DM']) / 60 : 0;
			$dec_stopnie += is_numeric($object['DS']) ? floatval($object['DS']) / 3600 : 0;
			if ($object['V'] =='-') $dec_stopnie = -$dec_stopnie; // Obsługa południowych deklinacji

			$szer_dziesietna = stopnieMinutySekundyNaStopnie($latitude);
			$dlug_dziesietna = stopnieMinutySekundyNaStopnie($longitude);
		    
			
						
			if (preg_match('/([+-])(\d{2}):(\d{2})/', $timezone, $matches)) {
				$znak = $matches[1];
				$godziny = intval($matches[2]);
				$minuty = intval($matches[3]);
				
				// Oblicz przesunięcie w sekundach
				$sekundy_przesuniecia = ($godziny * 3600) + ($minuty * 60);
				if ($znak === '-') {
					$sekundy_przesuniecia = -$sekundy_przesuniecia;
				}

				// Dodaj przesunięcie do czasu
				$czas_z_timezone = date('H:i', strtotime($time) - $sekundy_przesuniecia);
				
			} else {
				$czas_z_timezone = $time;  // Jeśli nie ma strefy czasowej, użyj oryginalnego czasu
			}
				
	
			
			
			$wspolrzedne = obliczAzymutWysokosc($ra_stopnie, $dec_stopnie, $szer_dziesietna, $dlug_dziesietna, $date, $czas_z_timezone);
			$azymut = round($wspolrzedne['azymut'], 2);
			$wysokosc = round($wspolrzedne['wysokosc'], 2);
			
			echo "<p style='margin: 2px 0;'><strong>Azymut:</strong> {$azymut}°</p>";
			echo "<p style='margin: 2px 0;'><strong>Wysokość:</strong> {$wysokosc}°</p>";
		} else {
			echo "<p style='margin: 2px 0;'><strong>Azymut:</strong> Brak danych</p>";
			echo "<p style='margin: 2px 0;'><strong>Wysokość:</strong> Brak danych</p>";
		}
			echo "<p style='margin: 2px 0;;'><strong>Inne nazwy:</strong> {$object['ID1']} {$object['ID2']} {$object['ID3']} {$object['ID4']} {$object['ID5']} {$object['ID6']} {$object['ID7']} {$object['ID8']} {$object['ID9']} {$object['ID10']} {$object['ID11']} </>";
		

		
		if ($showImages == '1') {
			if($object['DSO']=="M") {
				// Generowanie nazw plików zdjęć
				$imageWide = "images/".$object['Image1'];
				$imageZoom = "images/".$object['Image2'];

				// Sprawdzamy, czy pliki obrazków istnieją
				if (!file_exists($imageWide)) {
					$imageWide = "images/notfound.jpg";
				}
				
				if (!file_exists($imageZoom)) {
					$imageZoom = "images/notfound.jpg";
				}

				// Wyświetlanie zdjęcia szerokiego ujęcia
				echo "<img src='$imageWide' alt='Wide view of Messier $mNumber' style='max-width: 100%; height: auto; margin-bottom: 10px;' onclick=\"openImageInPopup(this)\">";
			   
				// Wyświetlanie zdjęcia powiększonego
				echo "<img src='$imageZoom' alt='Zoom view of Messier $mNumber' style='max-width: 100%; height: auto;' onclick=\"openImageInPopup(this)\">";
			}
			if($object['DSO']=="C") {
				// Generowanie nazw plików zdjęć
				$imageWide = "images/".$object['Image1'];

				// Sprawdzamy, czy pliki obrazków istnieją
				if (!file_exists($imageWide)) {
					$imageWide = "images/notfound.jpg";
				}
				
				// Wyświetlanie zdjęcia szerokiego ujęcia
				echo "<img src='$imageWide' alt='Wide view of Messier $mNumber' style='max-width: 100%; height: auto; margin-bottom: 10px;' onclick=\"openImageInPopup(this)\">";
			}
			if($object['DSO']=="N") {
				// Generowanie nazw plików zdjęć
				$imageWide = $object['nImage1'];

				// Wyświetlanie zdjęcia szerokiego ujęcia
				echo "<img src='$imageWide' alt='Wide view of Messier $mNumber' style='max-width: 100%; height: auto; margin-bottom: 10px;' onclick=\"openImageInPopup(this)\">";
			}
		}
        echo "</div>";
    }

    echo "</div>";
	


    ?>
    <script>
	
	var imageCheckbox = document.querySelector('input[name="zdjecia"]');
	imageCheckbox.addEventListener('change', function() {
		document.getElementById('filterForm').submit();
	});
	// Obsługa przycisków radio dla widzialności
	var visibilityRadios = document.querySelectorAll('input[name="visibility"]');
	visibilityRadios.forEach(function(radio) {
		radio.addEventListener('change', function() {
			var horizonWidget = document.getElementById('horizon-widget');
			var altitudeWidget = document.getElementById('altitude-widget');
			if (this.value === 'in_area') {
				horizonWidget.style.display = 'block';
				altitudeWidget.style.display = 'block';
			} else {
				horizonWidget.style.display = 'none';
				altitudeWidget.style.display = 'none';
			}
			document.getElementById('filterForm').submit();
		});
	});

	// Inicjalizacja suwaka wysokości
	var altitudeSlider = document.getElementById('altitude-slider');
	noUiSlider.create(altitudeSlider, {
		start: [<?php echo $altitudeStart; ?>, <?php echo $altitudeEnd; ?>],
		connect: true,
		range: {
			'min': 0,
			'max': 90
		},
		tooltips: [true, true],
		step: 1
	});

	altitudeSlider.noUiSlider.on('update', function(values, handle) {
		document.getElementById('altitude_start').value = Math.round(values[0]);
		document.getElementById('altitude_end').value = Math.round(values[1]);
	});

	altitudeSlider.noUiSlider.on('set', function() {
		document.getElementById('filterForm').submit();
	});
    document.getElementById('searchLocation').addEventListener('click', function() {
        var location = document.getElementById('location').value;
        
        // Funkcja do konwersji stopni dziesiętnych na format DMS
        function convertToDMS(deg) {
            var d = Math.floor(Math.abs(deg));
            var minfloat = (Math.abs(deg)-d)*60;
            var m = Math.floor(minfloat);
            var secfloat = (minfloat-m)*60;
            var s = Math.round(secfloat);
            
            // Obsługa przypadku, gdy sekundy są zaokrąglone do 60
            if (s === 60) {
                m++;
                s = 0;
            }
            if (m === 60) {
                d++;
                m = 0;
            }
            
            return d + "° " + m + "' " + s + '"';
        }

        // Użycie API OpenStreetMap Nominatim do geokodowania
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    // Pobierz pierwsze znalezione miejsce
                    var place = data[0];
                    var latDMS = convertToDMS(parseFloat(place.lat));
                    var lonDMS = convertToDMS(parseFloat(place.lon));
                    document.getElementById('latitude').value = latDMS;
                    document.getElementById('longitude').value = lonDMS;
                    alert(`Znaleziono współrzędne dla ${location}:\nSzerokość: ${latDMS}\nDługość: ${lonDMS}`);
                    document.getElementById('filterForm').submit();
                } else {
                    alert('Nie znaleziono współrzędnych dla podanej lokalizacji.');
                }
            })
            .catch(error => {
                console.error('Błąd podczas wyszukiwania:', error);
                alert('Wystąpił błąd podczas wyszukiwania współrzędnych.');
            });
    });

    // Funkcja do ustawiania aktualnej daty i czasu
    document.getElementById('setNow').addEventListener('click', function() {
        var now = new Date();
        var dateInput = document.getElementById('date');
        var timeInput = document.getElementById('time');
        var timezoneInput = document.getElementById('timezone');
        
	
        // Formatowanie daty (YYYY-MM-DD)
        var year = now.getFullYear();
        var month = (now.getMonth() + 1).toString().padStart(2, '0');
        var day = now.getDate().toString().padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}`;
        
        // Formatowanie czasu (HH:MM)
        var hours = now.getHours().toString().padStart(2, '0');
        var minutes = now.getMinutes().toString().padStart(2, '0');
        timeInput.value = `${hours}:${minutes}`;
        
		    // Dodanie obsługi strefy czasowej
		var timezoneOffset = -now.getTimezoneOffset(); // Strefa czasowa w minutach od UTC
		var timezoneHours = Math.floor(timezoneOffset / 60).toString().padStart(2, '0');
		var timezoneMinutes = (Math.abs(timezoneOffset) % 60).toString().padStart(2, '0');
		var timezoneFormatted = (timezoneOffset >= 0 ? '+' : '-') + timezoneHours + ':' + timezoneMinutes;
		timezoneInput.value = timezoneFormatted;
	
        
		
        document.getElementById('filterForm').submit();
    });

    var magSlider = document.getElementById('magSlider');
    noUiSlider.create(magSlider, {
        start: [<?php echo $minMag; ?>, <?php echo $maxMag; ?>],
        connect: true,
        range: {
            'min': <?php echo $minMagDefault; ?>,
            'max': <?php echo $maxMagDefault; ?>
        },
        tooltips: [true, true],
        step: 0.1
    });

    magSlider.noUiSlider.on('update', function(values, handle) {
        document.getElementById('min_mag_val').textContent = values[0];
        document.getElementById('max_mag_val').textContent = values[1];
        document.getElementById('min_mag').value = values[0];
        document.getElementById('max_mag').value = values[1];
    });

    var sizeXSlider = document.getElementById('sizeXSlider');
    noUiSlider.create(sizeXSlider, {
        start: [<?php echo $minSizeX; ?>, <?php echo $maxSizeX; ?>],
        connect: true,
        range: {
            'min': <?php echo $minSizeXDefault; ?>,
            'max': <?php echo $maxSizeXDefault; ?>
        },
        tooltips: [true, true],
        step: 0.1
    });

    sizeXSlider.noUiSlider.on('update', function(values, handle) {
        document.getElementById('min_size_x_val').textContent = values[0];
        document.getElementById('max_size_x_val').textContent = values[1];
        document.getElementById('min_size_x').value = values[0];
        document.getElementById('max_size_x').value = values[1];
    });

    var sizeYSlider = document.getElementById('sizeYSlider');
    noUiSlider.create(sizeYSlider, {
        start: [<?php echo $minSizeY; ?>, <?php echo $maxSizeY; ?>],
        connect: true,
        range: {
            'min': <?php echo $minSizeYDefault; ?>,
            'max': <?php echo $maxSizeYDefault; ?>
        },
        tooltips: [true, true],
        step: 0.1
    });

    sizeYSlider.noUiSlider.on('update', function(values, handle) {
        document.getElementById('min_size_y_val').textContent = values[0];
        document.getElementById('max_size_y_val').textContent = values[1];
        document.getElementById('min_size_y').value = values[0];
        document.getElementById('max_size_y').value = values[1];
    });

    // Automatyczne przesłanie formularza po puszczeniu suwaków
    magSlider.noUiSlider.on('set', function() {
        document.getElementById('filterForm').submit();
    });
    sizeXSlider.noUiSlider.on('set', function() {
        document.getElementById('filterForm').submit();
    });
    sizeYSlider.noUiSlider.on('set', function() {
        document.getElementById('filterForm').submit();
    });

    // Automatyczne przesyłanie formularza po kliknięciu w checkbox
    var checkboxes = document.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // Automatyczne przesyłanie formularza po zmianie przycisku radio
    var radioButtons = document.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });

    // Funkcja otwierająca obrazek w nowym oknie popup
    function openImageInPopup(image) {
        var img = new Image();
        img.src = image.src;

        // Gdy obrazek zostanie załadowany, pobieramy jego rozmiar i otwieramy nowe okno
        img.onload = function() {
            var width = img.width;
            var height = img.height;
            window.open(image.src, 'Obrazek', 'width=' + width + ',height=' + height);
        };
    }
    var dsoCheckboxes = document.querySelectorAll('input[name="dso[]"]');
    dsoCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
	
	const canvas = document.getElementById("azimuth-canvas");
const ctx = canvas.getContext("2d");
const radius = canvas.width / 2 - 20;
const centerX = canvas.width / 2;
const centerY = canvas.height / 2;
let angle1 = <?php echo $azimuthStart; ?>;
let angle2 = <?php echo $azimuthEnd; ?>;

let dragging = null;

const drawSlider = () => {
	


    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Narysuj okrąg
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
    ctx.strokeStyle = "#ddd";
    ctx.lineWidth = 10;
    ctx.stroke();

    // Narysuj zakres między dwoma suwakami (kolorowy odcinek)
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, (angle1 - 90) * Math.PI / 180, (angle2 - 90) * Math.PI / 180);
    ctx.strokeStyle = "#5dc6b5";
    ctx.lineWidth = 10;
    ctx.stroke();

    // Narysuj uchwyt dla suwaka 1
    drawHandle(angle1, "#ff5722");

    // Narysuj uchwyt dla suwaka 2
    drawHandle(angle2, "#03a9f4");



    // Narysuj kierunki N, E, S, W
    ctx.fillStyle = "#000"; // Kolor tekstu (czarny)
    ctx.font = "bold 16px Arial"; // Styl tekstu (pogrubiona czcionka 16px)

    // Pozycje dla N, E, S, W
    ctx.fillText("N", centerX - 8, centerY - radius + 25); // N (na górze)
    ctx.fillText("E", centerX + radius - 25, centerY + 5); // E (po prawej)
    ctx.fillText("S", centerX - 8, centerY + radius - 15); // S (na dole)
    ctx.fillText("W", centerX - radius + 10, centerY + 5); // W (po lewej)


    // Zaktualizuj ukryte pola formularza
    document.getElementById("azimuth_start").value = Math.round(angle1);
    document.getElementById("azimuth_end").value = Math.round(angle2);
	
	document.getElementById('label-a').innerHTML = Math.round(angle1, 2, 0);
	document.getElementById('label-b').innerHTML = Math.round(angle2, 2, 0);
};

const drawHandle = (angle, color) => {
    const radian = (angle - 90) * Math.PI / 180;
    const x = centerX + radius * Math.cos(radian);
    const y = centerY + radius * Math.sin(radian);

    ctx.beginPath();
    ctx.arc(x, y, 10, 0, 2 * Math.PI);
    ctx.fillStyle = color;
    ctx.fill();
    ctx.strokeStyle = "#fff";
    ctx.lineWidth = 2;
    ctx.stroke();
};

const getAngleFromEvent = (x, y) => {
    const rect = canvas.getBoundingClientRect();
    const dx = x - rect.left - centerX;
    const dy = y - rect.top - centerY;
    const angle = Math.atan2(dy, dx) * 180 / Math.PI + 90;
    return (angle < 0 ? angle + 360 : angle);
};

const startDrag = (x, y) => {
    const angle = getAngleFromEvent(x, y);
    const dist1 = Math.abs(angle - angle1);
    const dist2 = Math.abs(angle - angle2);

    if (dist1 < dist2 && dist1 < 30) {
        dragging = "angle1";
    } else if (dist2 < dist1 && dist2 < 30) {
        dragging = "angle2";
    }
	//document.getElementById('label-a').innerHTML = Math.round(angle1, 2, 0);
	//document.getElementById('label-b').innerHTML = Math.round(angle2, 2, 0);
};

const moveDrag = (x, y) => {
    if (dragging) {
        const angle = getAngleFromEvent(x, y);
        if (dragging === "angle1") {
            angle1 = angle;
        } else if (dragging === "angle2") {
            angle2 = angle;
        }
        drawSlider();

		//document.getElementById('label-a').innerHTML = Math.round(angle1, 2, 0);
		//document.getElementById('label-b').innerHTML = Math.round(angle2, 2, 0);
    }
};

const endDrag = () => {
    if (dragging) {
        dragging = null;
        document.getElementById('filterForm').submit();
		//document.getElementById('label-a').innerHTML = Math.round(angle1, 2, 0);
		//document.getElementById('label-b').innerHTML = Math.round(angle2, 2, 0);
    }
};

// Obsługa myszy
canvas.addEventListener("mousedown", (event) => startDrag(event.clientX, event.clientY));
canvas.addEventListener("mousemove", (event) => moveDrag(event.clientX, event.clientY));
canvas.addEventListener("mouseup", endDrag);
canvas.addEventListener("mouseleave", endDrag);

// Obsługa dotyku
canvas.addEventListener("touchstart", (event) => {
    const touch = event.touches[0];
    startDrag(touch.clientX, touch.clientY);
});

canvas.addEventListener("touchmove", (event) => {
    const touch = event.touches[0];
    moveDrag(touch.clientX, touch.clientY);
});

canvas.addEventListener("touchend", endDrag);

// Inicjalizacja suwaka
drawSlider();




document.getElementById('generateMap').addEventListener('click', function() {
    var form = document.getElementById('filterForm');
    var mapObjectsCount = form.elements['map_objects_count'].value;
    var mapSize = form.elements['map_size'].value;
    
    var url = 'index.php?' + new URLSearchParams(new FormData(form)).toString() + 
              '&generate_map=1' + 
              '&map_objects_count=' + mapObjectsCount + 
              '&map_size=' + mapSize;
    
    window.open(url, '_blank');
});


    </script>
</body>
</html>