<?php

/* NOTE
 *
 * R = matrix r = normalized matrix
 * w = weight
 * V or v = weighted normalized matrix 
 * C/c = concordance
 * D/d = discordance
 * F/f = dominant matrix concordance
 * D/d = dominant matrix discordance
 *
 */

class Electre_algorithm {

	protected $object;
	protected $rows;
	protected $columns;
	protected $columns_arr;

	public function __construct($object = NULL, $columns_arr = NULL, $rows = NULL, $columns = NULL) {
		$this->object = $object;
		$this->rows = $rows;
		$this->columns = $columns;
		$this->columns_arr = $columns_arr;
	}

	/* STEP 1
	 * Normalization matrix / 
	*/
	public function normalization() {
		// $example = 5 / sqrt(5*5 + 4*4 + 2*2 + 3*3 + 4*4);
		// $normalization = round($example, 2);

		// $columns = [[4,4,5,4], [5,4,4,3], [4,3,2,3], [2,3,4,5,], [3,5,3,2]];
		$columns = $this->columns_arr;
		$squared_arr = [];
		$squared_arr_sum = [];
		
		for ($row = 0; $row <= $this->rows; $row++) { 
			for ($column = 0; $column < count($columns[$row]); $column++) { 
				$squared = $columns[$row][$column] * $columns[$row][$column];
				$squared_arr[$row][] = $squared;
			}
			
			$squared_arr_sum[$row] = array_sum($squared_arr[$row]);
		}

		foreach ($columns as $key_row => $row) {
			foreach ($row as $key_col => $col) {
				$calculate = $col / sqrt($squared_arr_sum[$key_row]);

				// normalized "r" matrix, example(r11, r21, r31..n)
				$key_r = 'r_'.($key_col + 1).'_'.($key_row + 1);
				$normalization_arr[$key_r] = $this->_number_format($calculate);
			}
		}
		
		$normalization_arr;
		/* create new row and column after you get normalization result
		 * based number of rows and numbers of columns
		*/
		$matrix = array_chunk($normalization_arr, $this->rows);

		return [$matrix, $normalization_arr];
	}

	/* STEP 2
	 * weighting on the normalized matrix
	*/
	public function weighting($matrix, $normalization) {
		// change key for object and including weight
		foreach ($this->object as $key => $value) {
			$weight_key = 'w_'.($value['bobot']);
			// $normalization_groups[$weight_key] = $normalization[$key]; 
			$normalization_groups[] = [$weight_key => $matrix[$key]]; 
			
		}

		$normalization_groups;

		// multiplication rows with weight
		foreach ($normalization_groups as $key_normalization => $normalization_group) {
			foreach ($normalization_group as $key_group => $group) {
				$weight = (int) explode('_', $key_group)[1];

				foreach ($group as $key => $value) {
					// normalized "v" weighting matrix, example(v11, v21, v31..n)
					$key_v = 'v_'.($key + 1).'_'.($key_normalization + 1);
					$weight_normalized_arr[$key_v] = $this->_number_format($value * $weight);
				}
			}
		}

		$weight_normalized_arr;
		$weight_normalized = array_chunk($weight_normalized_arr, $this->rows);
		
		return [$weight_normalized, $weight_normalized_arr];
	}

	/* STEP 3
	 * Determine concordance and discordance sets.
	 * ============================================================================
	 * Determine C11, C12, C13, etc as many as the number of matrix
	 * and then compare with Y21, Y22, Y23 etc as many as the number of matrix
	 * description: C1(K)1(L), C1(K)2(L), Y1(L)1(J), Y2(L)2(J) etc
	 * 
	 * ex: if c11 >= y2,1 = value c11(3,201) >= y2,1 (2,561) = true (greather than)
	 *
	*/
	public function concordance_and_discordance($matrix_v, $weight_normalized_arr) {
		$concordance = $this->concordance($matrix_v, $weight_normalized_arr);
		$discordance = $this->discordance($matrix_v, $weight_normalized_arr);
		
		return [$concordance, $discordance];
	}

	/* STEP 4
	 * Calculate matrix concordance and matrix discordance based on weight
	*/
	public function calculate_concordance_and_discordance($matrix_v, $concordance, $discordance) {
		list($matrix_c, $concordance_arr) = $this->calcutating_concordance($matrix_v, $concordance);
		list($matrix_d, $discordance_arr) = $this->calcutating_discordance($matrix_v, $discordance);

		return [$matrix_c, $concordance_arr, $matrix_d, $discordance_arr];
	}

	/* STEP 5 
	 * determine dominant matrix concordance and discordance
	*/
	public function dominant_matrix($matrix_c, $matrix_d) {
		foreach ($matrix_c as $key => $value) {
			$sum_matrix_c[] = array_sum($value);
		}

		foreach ($matrix_d as $key => $value) {
			$sum_matrix_d[] = array_sum($value);
		}

		$threshold_c = array_sum($sum_matrix_c) / (($this->rows * ($this->rows - 1)));
		$threshold_c = $this->_number_format($threshold_c);
		$matrix_f = $this->get_dominant_matrix($matrix_c, $threshold_c);
		
		$threshold_d = array_sum($sum_matrix_d) / (($this->rows * ($this->rows - 1)));
		$threshold_d = $this->_number_format($threshold_d);
		$matrix_g = $this->get_dominant_matrix($matrix_d, $threshold_d);
		
		return [$matrix_f, $matrix_g];
	}

	/* STEP 6
	 * determinat aggregate dominance matrix
	*/
	public function dominant_aggregation($matrix_f, $matrix_g) {
		$matrix_e_arr = $this->set_array_keys('e');

		foreach ($matrix_e_arr as $key => $value) {
			$index = explode('_', $key);
			$arr_matrix_e[$key] = $matrix_f[$index[1]][$index[2]] * $matrix_g[$index[1]][$index[2]];
		}

		$matrix_e = $this->get_concordance_discordance_matrix($arr_matrix_e);
		
		return [$matrix_e, $arr_matrix_e];
	}

	/* STEP 7
	 * elimination alternative less favourable
	*/
	public function eliminations($matrix_e, $arr_matrix_e) {
		/* later will be used
		for ($row=0; $row <= $this->rows; $row++) {
			for ($column=0; $column < $this->rows; $column++) { 
				if ($row == $column) {
					$table[$row + 1][$column + 1] = '-';
				} else {
					$table[$row + 1][$column + 1] = NULL;
				}
			}
		}
		
		$arr_list = $table;
		unset($arr_list[count($table)]);
		
		foreach ($arr_matrix_e as $key_object => $value_object) {
			$index = explode('_', $key_object);
			$arr_list[$index[1]][$index[2]] = $value_object;
		}
		*/
		
		foreach ($matrix_e as $key => $value) {
			$arr_count[$key] = count(array_keys($matrix_e[$key], 1));
		}

		$arr_eliminate_count = $arr_count;
		
		/* later will be use
		$arr_count = array_diff($arr_count, [0]);

		$i = 0;
		foreach ($arr_count as $key => $value) {
			if ($value > $i) {
				$i = $value;
				$arr_eliminate = [$key => $arr_count[$key]];
			}
		}
		*/

		return $arr_eliminate_count;
	}

	public function rating_result($alternative, $arr_eliminate_count) {
		$i = 1;
		foreach ($alternative as $key => $value) {
			$ratings[$key] = $arr_eliminate_count[$i++];
		}

		arsort($ratings);
		
		$number = 1;
		foreach ($ratings as $key => $value) {
			$ratings[$key] = $number++;
		}
		
		return $ratings;
	}


	/* INCLUDING OF STEP 3*/
	protected function concordance($matrix_v, $weight_normalized_arr) {
		$concordance_arr = $this->set_array_keys('c');
		$i = 1;

		foreach ($concordance_arr as $key_concordance => $concordance_value) {
			$split_key = explode('_', $key_concordance);
			$index_concordance = [];

			for ($column = 0; $column < $this->columns; $column++) {
				$col = $column + 1;
				list($pattern_c, $pattern_y) = $this->set_pattern_weighting_key('v', $split_key, $col);

				if ($weight_normalized_arr[$pattern_c] >= $weight_normalized_arr[$pattern_y]) {
					array_push($index_concordance, $col);
					$concordance[$key_concordance] = $index_concordance;
				}
			}

			$i++;
		}
		
		return $concordance;
	}

	/* INCLUDING OF STEP 3*/
	protected function discordance($matrix_v, $weight_normalized_arr) {
		$discordance_arr = $this->set_array_keys('d');
		$i = 1;

		foreach ($discordance_arr as $key_discordance => $discordance_value) {
			$split_key = explode('_', $key_discordance);
			$index_discordance = [];

			for ($column = 0; $column < $this->columns; $column++) {
				$col = $column + 1;
				list($pattern_c, $pattern_y) = $this->set_pattern_weighting_key('v', $split_key, $col);

				if ($weight_normalized_arr[$pattern_c] < $weight_normalized_arr[$pattern_y]) {
					array_push($index_discordance, $col);
					$discordance[$key_discordance] = $index_discordance;
				}
			}

			$i++;
		}
		
		return $discordance;
	}

	/* INCLUDING OF STEP 4 */
	protected function calcutating_concordance($matrix_v, $concordance) {
		$concordance_arr = $this->set_array_keys('c');
		$weight_columns = $this->create_weight_column($matrix_v);

		foreach ($concordance as $key_concordance => $value_concordance) {
			foreach ($value_concordance as $key => $value) {
				$new_concordance_arr[$key_concordance][] = $weight_columns[$value];
			}
		}

		foreach ($new_concordance_arr as $key => $value) {
			$concordance_arr[$key] = array_sum($new_concordance_arr[$key]);
		}
		
		$matrix_c = $this->get_concordance_discordance_matrix($concordance_arr);
		
		return [$matrix_c, $concordance_arr];
	}

	/* INCLUDING OF STEP 4 */
	protected function calcutating_discordance($matrix_v, $discordance) {
		$discordance_arr = $this->set_array_keys('d');
		$replace_matrix_v = $this->replace_key_matrix_v($matrix_v);

		foreach ($discordance as $key_discordance => $value_discordance) {
			foreach ($value_discordance as $key => $value) {
				$new_discordance_arr[$key_discordance][$value] = $replace_matrix_v[$value];
			}
		}

		foreach ($new_discordance_arr as $key_discordance => $value_discordance) {
			$index = explode('_', $key_discordance);

			foreach ($value_discordance as $key_col => $col_value) {
				$differences[$key_discordance][$key_col] = abs($col_value[$index[1] - 1] - $col_value[$index[2] - 1]);

				foreach ($replace_matrix_v as $key_v => $value_v) {
					$all_differences[$key_discordance][] = abs($value_v[$index[1] - 1] - $value_v[$index[2] - 1]);
				}
			}
		}
		
		$max_differences = $this->set_differences_matrix($differences);
		$all_max_differences = $this->set_differences_matrix($all_differences);

		foreach ($max_differences as $key_difference => $difference) {
			foreach ($all_max_differences as $key_all_difference => $all_difference) {
				if ($key_difference == $key_all_difference) {
					$discordance_arr[$key_difference] = $this->_number_format($difference / $all_difference);
				}
			}
		}
		
		$matrix_d = $this->get_concordance_discordance_matrix($discordance_arr);
		
		return [$matrix_d, $discordance_arr];
	}

	/* Setting array
	 * ex: array('c_1_1' => array(), 'c_1_2' => array());
	 * note: c or d similar to concordance and discordance
	*/
	private function set_array_keys($pattern) {
		for ($row = 0; $row < $this->rows; $row++) { 
			for ($column = 0; $column < $this->rows; $column++) { 
				if ($row != $column) {
					$key = $pattern.'_'.($row + 1).'_'.($column + 1);
					$args[$key] = NULL;
				}
			}
		}

		return $args;
	}

	private function _number_format($number) {
		return number_format($number, 3, '.', '');
	}

	private function set_pattern_weighting_key($pattern, $object, $column) {
		$pattern_c = $pattern.'_'.$object[1].'_'.$column;
		$pattern_y = $pattern.'_'.$object[2].'_'.$column;

		return [$pattern_c, $pattern_y];
	}

	/*
	 * create column based on weight or criteria
	 * ex: column C1 = weight 5, column C2 = weight 4
	*/
	private function create_weight_column() {
		foreach ($this->object as $key => $value) {
			for ($column=0; $column < $this->columns ; $column++) { 
				if ($key == $column) {
					$weight_columns[($column + 1)] = $value['bobot'];
				}
			}
		}

		return $weight_columns;
	}

	/*
	 * replace array index starting form 0,1,2,3 to 1,2,3,
	*/
	private function replace_key_matrix_v($matrix_v) {
		foreach ($matrix_v as $key_v => $matric_v) {
			for ($i = 0; $i < count($matric_v); $i++) { 
				$matrix[$key_v+1][] = $matric_v[$i];
			}
		}
		
		return $matrix;
	}

	/* set differences matrix */
	private function set_differences_matrix($differences) {
		foreach ($differences as $key => $difference) {
			$matrix[$key] = max($difference);
		}

		return $matrix;
	}

	/* get concordance matrix and discordance matrix */
	private function get_concordance_discordance_matrix($object) {
		$arr_list = $this->create_matrix_table();
		
		foreach ($object as $key_object => $value_object) {
			$index = explode('_', $key_object);
			$arr_list[$index[1]][$index[2]] = $value_object;
		}

		return $arr_list;
	}

	private function create_matrix_table() {
		for ($row=0; $row < $this->rows; $row++) {
			for ($column=0; $column < $this->rows; $column++) { 
				if ($row == $column) {
					$table[$row + 1][$column + 1] = '-';
				} else {
					$table[$row + 1][$column + 1] = NULL;
				}
			}
		}

		return $table;
	}

	/* INCLUDING STEP 5 */
	private function get_dominant_matrix($matrix, $threshold) {
		$matrix_table = $this->create_matrix_table();
		
		foreach ($matrix as $key_matrix => $matrix_list) {
			foreach ($matrix_list as $key => $matric) {
				if ($matric == '-') {
					$matrix_dominant[$key_matrix][$key] = '-';	
				} else {
					if ($matric >= $threshold) {
						$matrix_dominant[$key_matrix][$key] = 1;	
					} else {
						$matrix_dominant[$key_matrix][$key] = 0;	
					}
				}
			}
		}
		
		return $matrix_dominant;
	}
}