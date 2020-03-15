<?php
require_once('includes/Electre_algorithm.php');
/* ---------------------------------------------
 * Konek ke database & load fungsi-fungsi
 * ------------------------------------------- */
require_once('includes/init.php');

/* ---------------------------------------------
 * Load Header
 * ------------------------------------------- */
$judul_page = 'Perankingan Menggunakan Metode ELECTRE';
 require_once('template-parts/header.php');

/* ---------------------------------------------
 * Set jumlah digit di belakang koma
 * ------------------------------------------- */
$digit = 4;

/* ---------------------------------------------
 * Fetch semua kriteria
 * ------------------------------------------- */
$query = $pdo->prepare('SELECT id_kriteria, kode, nama_kriteria, bobot
	FROM kriteria ORDER BY urutan_order ASC');
$query->execute();
$query->setFetchMode(PDO::FETCH_ASSOC);
$kriterias = $query->fetchAll();

/* ---------------------------------------------
 * Fetch semua alternatif (alternatif)
 * ------------------------------------------- */
$query2 = $pdo->prepare('SELECT id_alternatif, kode_alternatif, nama_alternatif FROM alternatif');
$query2->execute();			
$query2->setFetchMode(PDO::FETCH_ASSOC);
$alternatifs = $query2->fetchAll();

$queryBaris = $pdo->prepare('SELECT COUNT(id_alternatif) as baris FROM alternatif');
$queryBaris->execute();
$queryBaris->setFetchMode(PDO::FETCH_ASSOC);
$baris = $queryBaris->fetch();

$queryKolom = $pdo->prepare('SELECT COUNT(id_kriteria) as kolom FROM kriteria');
$queryKolom->execute();
$queryKolom->setFetchMode(PDO::FETCH_ASSOC);
$kolom = $queryKolom->fetch();

$matriks_bobot = array();
foreach($kriterias as $bobot):
	array_push($matriks_bobot,$bobot['bobot']);	
endforeach;

$list_ctr = array();
foreach($kriterias as $list):
	array_push($list_ctr,$list['kode']);	
endforeach;

$list_alt = array();
foreach($alternatifs as $alt):
	array_push($list_alt,$alt['kode_alternatif']);	
endforeach;

$list_nama = array();
foreach($alternatifs as $alt1):
	array_push($list_nama,$alt1['nama_alternatif']);	
endforeach;



/* >>> STEP 1 ===================================
 * Matrix Keputusan (X)
 * ------------------------------------------- */
$matriks_x = array();
foreach($kriterias as $kriteria):
	foreach($alternatifs as $alternatif):
		
		$id_alternatif = $alternatif['id_alternatif'];
		$id_kriteria = $kriteria['id_kriteria'];
		
		// Fetch nilai dari db
		$query3 = $pdo->prepare('SELECT nilai FROM nilai_alternatif
			WHERE id_alternatif = :id_alternatif AND id_kriteria = :id_kriteria');
		$query3->execute(array(
			'id_alternatif' => $id_alternatif,
			'id_kriteria' => $id_kriteria,
		));			
		$query3->setFetchMode(PDO::FETCH_ASSOC);
		if($nilai_alternatif = $query3->fetch()) {
			// Jika ada nilai kriterianya
			$matriks_x[$id_kriteria][$id_alternatif] = $nilai_alternatif['nilai'];
		} else {			
			$matriks_x[$id_kriteria][$id_alternatif] = 0;
		}

	endforeach;
endforeach;

/* >>> STEP 3 ===================================
 * Matriks Ternormalisasi (R)
 * ------------------------------------------- */
$matriks_r = array();
foreach($matriks_x as $id_kriteria => $nilai_alternatifs):
	
	// Mencari akar dari penjumlahan kuadrat
	$jumlah_kuadrat = 0;
	foreach($nilai_alternatifs as $nilai_alternatif):
		$jumlah_kuadrat += pow($nilai_alternatif, 2);
	endforeach;
	$akar_kuadrat = sqrt($jumlah_kuadrat);
	
	// Mencari hasil bagi akar kuadrat
	// Lalu dimasukkan ke array $matriks_r
	foreach($nilai_alternatifs as $id_alternatif => $nilai_alternatif):
		$matriks_r[$id_kriteria][$id_alternatif] = $nilai_alternatif / $akar_kuadrat;
	endforeach;
	
endforeach;


/* >>> STEP 4 ===================================
 * Matriks Y
 * ------------------------------------------- */
$matriks_y = array();
foreach($kriterias as $kriteria):
	foreach($alternatifs as $alternatif):
		
		$bobot = $kriteria['bobot'];
		$id_alternatif = $alternatif['id_alternatif'];
		$id_kriteria = $kriteria['id_kriteria'];
		
		$nilai_r = $matriks_r[$id_kriteria][$id_alternatif];
		$matriks_y[$id_kriteria][$id_alternatif] = $bobot * $nilai_r;

	endforeach;
endforeach;


/* >>> STEP 5 ================================
 * Solusi Ideal Positif & Negarif
 * ------------------------------------------- */
$matriks_concordance = array();
$matriks_discordance = array();
$matriks_baru = array();
$k=0;
$l=0;
$jumlah_cond = 0;
$jc = array();
foreach($alternatifs as $alternatif): 						
		foreach($kriterias as $kriteria):
			$id_alternatif = $alternatif['id_alternatif'];
			$id_kriteria = $kriteria['id_kriteria'];
			$bobot1 = $kriteria['bobot'];

			array_push($matriks_baru, $matriks_y[$id_kriteria][$id_alternatif]);
			
			$l++;
		endforeach;
	$k++; 
endforeach;


$weight_arr = array();
$o=0;
for ($i=0; $i < $baris['baris'] ; $i++) { 
	for ($j=0; $j < $kolom['kolom'] ; $j++) { 
		$weight_arr[$i][$j]= $matriks_baru[$o];
		$o++;
	}
}

//Concordance
$c_arr = array();
$jum_tot_c = 0;
$tot_c=0;
for ($c1=0; $c1 < $baris['baris']; $c1++) { 
	for ($c2=0; $c2 < $baris['baris']; $c2++) {
		$jum_c1 = 0;
		if($c1!=$c2){
			for($c3=0;$c3<$kolom['kolom'];$c3++){
				if($weight_arr[$c1][$c3]>=$weight_arr[$c2][$c3]){
					$jum_c1 = $jum_c1 + $matriks_bobot[$c3];
				}
			}
		}else {
			$jum_c1 = 0;
		}
		
		$c_arr[$c1][$c2] = $jum_c1;
	}
}


//Discordance
$d_arr = array();
$n_atas = array();
$n_bawah = array();
$jum_tot_d = 0;
$tot_d=0;
for ($d1=0; $d1 < $baris['baris']; $d1++) { 
	for ($d2=0; $d2 < $baris['baris']; $d2++) {
		$jum_d1 = 0;
		unset($n_atas);
		unset($n_bawah);
		$n_atas = array();
		$n_bawah = array();
		if($d1!=$d2){
			for($d3=0;$d3<$kolom['kolom'];$d3++){
				if($weight_arr[$d1][$d3]<$weight_arr[$d2][$d3]){
					array_push($n_atas, (abs($weight_arr[$d1][$d3]-$weight_arr[$d2][$d3])));
				}
				array_push($n_bawah,(abs($weight_arr[$d1][$d3]-$weight_arr[$d2][$d3])));
			}
			if(max($n_bawah)==0){
				$jum_d=0;
			}if($n_atas==NULL){
				$jum_d=0;
			}else{
				$jum_d = max($n_atas)/max($n_bawah);
			}
			
			if(is_nan($jum_d)){
				$jum_d1=0;
			}else{
				$jum_d1 = $jum_d;
			}
		}else {
			$jum_d1 = 0;
		}
		
		$d_arr[$d1][$d2] = $jum_d1;
	}
}

//ThresHold concordance Discordance
for ($i=0; $i < $baris['baris'] ; $i++) {						
	for ($j=0; $j < $baris['baris'] ; $j++) { 
			$jum_tot_c = $jum_tot_c + $c_arr[$i][$j];
			$tot_c++;
		} 
}
$threshold_c = $jum_tot_c/(3*(3-1));


for ($i=0; $i < $baris['baris'] ; $i++) {						
		for ($j=0; $j < $baris['baris'] ; $j++) { 
				$jum_tot_d = $jum_tot_d + $d_arr[$i][$j];
				$tot_d++;
			$l++;
			} 
	}
$threshold_d = $jum_tot_d/(3 *(3 - 1));

//dominan Matriks
$mat_dom_c = array();
for ($i=0; $i < $baris['baris'] ; $i++) {						
	for ($j=0; $j < $baris['baris'] ; $j++) { 
			if($c_arr[$i][$j]>=$threshold_c){
				$mat_dom_c[$i][$j]=1;
			}else{
				$mat_dom_c[$i][$j]=0;
			}
		} 
}

$mat_dom_d = array();
for ($i=0; $i < $baris['baris'] ; $i++) {						
	for ($j=0; $j < $baris['baris'] ; $j++) { 
			if($d_arr[$i][$j]>=$threshold_d){
				$mat_dom_d[$i][$j]=1;
			}else{
				$mat_dom_d[$i][$j]=0;
			}
		} 
}

//agregate Dominan Matriks
$mat_hasil = array();
for ($i=0; $i<sizeof($mat_dom_c); $i++) {
	for ($j=0; $j<sizeof($mat_dom_d); $j++) {
		$mat_hasil[$i][$j] = $mat_dom_c[$i][$j]*$mat_dom_d[$i][$j];
	}
}

$mat_hasil_baru=array();

 
?>

<div class="main-content-row">
<div class="container clearfix">	

	<div class="main-content main-content-full the-content">
		
		<h1><?php echo $judul_page; ?></h1>
		
		<!-- STEP 1. Matriks Keputusan(X) ==================== -->		
		<h3>Step 1: Matriks Keputusan (X)</h3>
		<table class="pure-table pure-table-striped">
			<thead>
				<tr class="super-top">
					<th rowspan="2" class="super-top-left">No. alternatif</th>
					<th colspan="<?php echo count($kriterias); ?>">Kriteria</th>
				</tr>
				<tr>
					<?php foreach($kriterias as $kriteria ): ?>
						<th><?php echo $kriteria['kode']; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach($alternatifs as $alternatif): ?>
					<tr>
						<td><?php echo $alternatif['kode_alternatif']; ?></td>
						<?php						
						foreach($kriterias as $kriteria):
							$id_alternatif = $alternatif['id_alternatif'];
							$id_kriteria = $kriteria['id_kriteria'];
							echo '<td>';
							echo $matriks_x[$id_kriteria][$id_alternatif];
							echo '</td>';
						endforeach;
						?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<!-- STEP 2. Bobot Preferensi (W) ==================== -->
		<h3>Step 2: Bobot Kriteria (W)</h3>			
		<table class="pure-table pure-table-striped">
			<thead>
				<tr>
					<th>Nama Kriteria</th>
					<th>Bobot (W)</th>						
				</tr>
			</thead>
			<tbody>
				<?php foreach($kriterias as $hasil): ?>
					<tr>
						<td><?php echo $hasil['kode']; ?></td>
						<td><?php echo $hasil['bobot']; ?></td>							
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<!-- Step 3: Matriks Ternormalisasi (R) ==================== -->
		<h3>Step 3: Matriks Ternormalisasi (R)</h3>			
		<table class="pure-table pure-table-striped">
			<thead>
				<tr class="super-top">
					<th rowspan="2" class="super-top-left">No. alternatif</th>
					<th colspan="<?php echo count($kriterias); ?>">Kriteria</th>
				</tr>
				<tr>
					<?php foreach($kriterias as $kriteria ): ?>
						<th><?php echo $kriteria['kode']; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach($alternatifs as $alternatif): ?>
					<tr>
						<td><?php echo $alternatif['kode_alternatif']; ?></td>
						<?php						
						foreach($kriterias as $kriteria):
							$id_alternatif = $alternatif['id_alternatif'];
							$id_kriteria = $kriteria['id_kriteria'];
							echo '<td>';
							echo round($matriks_r[$id_kriteria][$id_alternatif], $digit);
							echo '</td>';
						endforeach;
						?>
					</tr>
				<?php endforeach; ?>				
			</tbody>
		</table>
		
		
		<!-- Step 4: Matriks V ==================== -->
		<h3>Step 4: Matriks Normalisasi Terbobot (V)</h3>			
		<table class="pure-table pure-table-striped">
			<thead>
				<tr class="super-top">
					<th rowspan="2" class="super-top-left">No. alternatif</th>
					<th colspan="<?php echo count($kriterias); ?>">Kriteria</th>
				</tr>
				<tr>
					<?php foreach($kriterias as $kriteria ): ?>
						<th><?php echo $kriteria['kode']; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php 
				$k=0;
				$l=0;
				foreach($alternatifs as $alternatif): ?>
					<tr>
						<td><?php echo $alternatif['kode_alternatif']; ?></td>
						<?php						
						foreach($kriterias as $kriteria):

							$id_alternatif = $alternatif['id_alternatif'];
							$id_kriteria = $kriteria['id_kriteria'];
							echo '<td>';
							echo round($matriks_y[$id_kriteria][$id_alternatif], $digit);
							echo '</td>';
							$l++;
						endforeach;
						?>
					</tr>
				<?php
				$k++; 
				endforeach; ?>	
			</tbody>
		</table>	
		
		
		<!-- Step 5.1: Solusi Ideal Positif ==================== -->
		<h3>Matriks Concordance </h3>			
		<table class="pure-table pure-table-striped">
			<thead>					
				<!-- <?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
						<td><?php  ?></td>
						<?php						
						for ($j=0; $j < $kolom['kolom'] ; $j++) { 

							echo '<td>';
							echo $weight_arr[$i][$j];
							echo '</td>';
						$l++;
						}
						?>
					</tr>
				<?php 
				} ?> -->
			</thead>
			<tbody>
			<?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
						<?php						
						for ($j=0; $j < $baris['baris'] ; $j++) { 

							echo '<td>';
							if($i==$j){
								echo "-------";
							}else{
							echo $c_arr[$i][$j];
							}
							echo '</td>';
						$l++;
						}
						?>
					</tr>
				<?php 
				} ?>					
			</tbody>
		</table>
		
		<!-- Step 5.2: Solusi Ideal negative ==================== -->
		<h3>Matriks Discordance </h3>			
		<table class="pure-table pure-table-striped">
			<thead>					
				
			</thead>
			<tbody>
			<?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
						<?php						
						for ($j=0; $j < $baris['baris'] ; $j++) { 

							echo '<td>';
							if($i==$j){
								echo "-------";
							}else{
								echo round($d_arr[$i][$j],$digit);
							}
							echo '</td>';
						$l++;
						}
						?>
					</tr>
				<?php 
				} ?>					
			</tbody>
		</table>		
		
		<!-- Step 6.1: Jarak Ideal Positif ==================== -->
		<h3>Matriks Concordance dominan </h3>
		<h4>Nilai ThresHold : <?php 
			echo round($threshold_c,$digit); 
			?>
		</h4>			
		<table class="pure-table pure-table-striped">
			<thead>					
				
			</thead>
			<tbody>
			<?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
						<?php						
						for ($j=0; $j < $baris['baris'] ; $j++) { 

							echo '<td>';
							if($i==$j){
								echo "-------";
							}else{
							echo $mat_dom_c[$i][$j];
							}
							echo '</td>';
						}
						?>
					</tr>
				<?php 
				} ?>
			</tbody>
		</table>
		
		<!-- Step 6.2: Jarak Ideal Negatif ==================== -->
		<h3>Matriks Discordance Dominan</h3>
		<h4>Nilai ThresHold : <?php 
			echo round($threshold_d,$digit); 
			?>
		</h4>			
		<table class="pure-table pure-table-striped">
			<thead>					

			</thead>
			<tbody>
			<?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
						<?php						
						for ($j=0; $j < $baris['baris'] ; $j++) { 

							echo '<td>';
							if($i==$j){
								echo "-------";
							}else{
							echo $mat_dom_d[$i][$j];
							}
							echo '</td>';
						}
						?>
					</tr>
				<?php 
				} ?>
			</tbody>
		</table>

		<h3>Matriks Agregate Dominan (Matriks E)</h3>			
		<table class="pure-table pure-table-striped">
			<thead>
				<tr>
					<th><?php  ?></th>					
				<?php 
					for ($i=0; $i < $baris['baris'] ; $i++) { 
							echo '<th style="padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #072e09; color: white;">';
							echo $list_alt[$i];
							echo '</th>';
						} ?>
					<th>total Ekl</th>
				</tr>
			</thead>
			<tbody>
			<?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
					<td style="padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #072e09; color: white;">
						<?php 
							echo $list_alt[$i];
						?>
					</td>
						<?php
						$tmp=0;						
						for ($j=0; $j < $baris['baris'] ; $j++) { 

							echo '<td>';
							if($i==$j){
								echo "-------";
							}else{
							echo $mat_hasil[$i][$j];
							}
							$tmp = $tmp + $mat_hasil[$i][$j];
							echo '</td>';
						}
						?>
						<td style="padding-top: 12px; padding-bottom: 12px; text-align: left; background-color: #0f5912; color: white;">
							<?php echo $tmp;
								array_push($mat_hasil_baru,$tmp);
							?>
						</td>
					</tr>
				<?php 
				} ?>
			</tbody>
		</table>
		
		<?php 
			$temp1=0;
			$temp2=0;
			$temp3=0;
			for($i=0;$i<count($list_alt);$i++){
				for($j=0;$j<count($list_alt);$j++){
					$a1=$list_alt[$i];
					$a2=$list_alt[$j];
					$r1=$mat_hasil_baru[$i];
					$r2=$mat_hasil_baru[$j];
					$n1=$list_nama[$i];
					$n2=$list_nama[$j];
					if($r1>$r2){
						$temp1=$r2;
						$r2=$r1;
						$mat_hasil_baru[$i]=$temp1;
						$mat_hasil_baru[$j]=$r2;
						$temp2=$a2;
						$a2=$a1;
						$list_alt[$i]=$temp2;
						$list_alt[$j]=$a2;
						$temp3=$n2;
						$n2=$n1;
						$list_nama[$i]=$temp3;
						$list_nama[$j]=$n2;
					}else{
						$mat_hasil_baru[$i]=$r1;
						$mat_hasil_baru[$j]=$r2;
						$list_alt[$i]=$a1;
						$list_alt[$j]=$a2;
						$list_nama[$i]=$n1;
						$list_nama[$j]=$n2;
					}
				}
			}
		
		?>
		<!-- Step 7: Perangkingan ==================== -->
		<h3>Ranking</h3>			
		<table class="pure-table pure-table-striped">
			<thead>
				<th>No</th>
				<th>Alternatif</th>
				<th>Nilai akhir</th>					
			</thead>
			<tbody>
			<?php 
				for ($i=0; $i < $baris['baris'] ; $i++) {
				?>
					<tr>
						<?php
							echo '<td>';
							echo $i+1;
							echo '</td>';						
							echo '<td>';
							echo $list_alt[$i].' ( '.$list_nama[$i].' )';
							echo '</td>';
							echo '<td>';
							echo $mat_hasil_baru[$i];
							echo '</td>';
						?>
					</tr>
				<?php 
				} ?>
			</tbody>
		</table>
		<h3>Kesimpulan</h3>
		<p>
		Matriks Agregate Dominan memberikan urutan pilihan dari setiap alternatif . Dengan demikian dapat dieleminasi dan tersisa baris dengan nilai terbanyak. Sehingga pengambil keputusan akan mengambil alternatif dengan nilai terbesar yaitu pada peringkat pertama terdapat pada alternatif kedua (Apartmen 2) dan disusul oleh alternatif 3 (Apartemen 3) dan peringkat terakhir pada alternatif 1 (Apartemen 1).
		</p>	
		
	</div>

</div><!-- .container -->
</div><!-- .main-content-row -->

<?php
// require_once('tem7plate-parts/footer.php');