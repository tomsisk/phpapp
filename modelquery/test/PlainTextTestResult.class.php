<?php
	/*
	 * ModelQuery - a simple ORM layer.
	 * Copyright (C) 2004 Jeremy Jongsma.  All rights reserved.
	 * Portions inspired by the OpenSymphony WebWork project.
	 * 
	 * This library is free software; you can redistribute it and/or
	 * modify it under the terms of the GNU Lesser General Public
	 * License as published by the Free Software Foundation; either
	 * version 2.1 of the License, or (at your option) any later version.
	 * 
	 * This library is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	 * Lesser General Public License for more details.
	 * 
	 * You should have received a copy of the GNU Lesser General Public
	 * License along with this library; if not, write to the Free Software
	 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
	 */

	class PlainTextTestResult extends TestResult {

		private $starttime = null;

		/* Specialized TestResult to produce text report */
		public function PlainTextTestResult() {
			$this->TestResult();  // call superclass constructor
			$this->starttime = $this->microtime_float();
			print("---------------------------------------------------------------------------------------\n");
			print("PHPUnit Test Run                                                     Time       Result\n");
			print("---------------------------------------------------------------------------------------\n");
		}

		public function report() {
			$passed = (sizeof($this->fFailures) == 0 && sizeof($this->fErrors) == 0);
			print("---------------------------------------------------------------------------------------\n");
			print("Summary");
			print("                                                          (");
			print($this->readableDuration($this->microtime_float() - $this->starttime));
			print(")  ".$this->passOrFail($passed)."\n");
			print("---------------------------------------------------------------------------------------\n");

			/* report result of test run */
			$nRun = $this->countTests();
			$nFailures = $this->failureCount();
			$nErrors = $this->errorCount();
			printf("Tests run:     %s\n", $nRun);
			printf("Failures:      %s\n", $nFailures);
			printf("Errors:        %s\n", $nErrors);

			if ($nFailures > 0) {
				print("\nFailures\n");
				$failures = $this->getFailures();
				while (list($i, $failure) = each($failures)) {
					$failedTestName = $failure->getTestName();
					printf(" * %s\n", $failedTestName);

					$exceptions = $failure->getExceptions();
					while (list($na, $exception) = each($exceptions))
					printf("   - %s\n", $exception->getMessage());
				}
			}

			if ($nErrors > 0) {
				print("\nErrors\n");
				reset($this->fErrors);
				while (list($i, $error) = each($this->fErrors)) {
					$erroredTestName = $error->getTestName();
					printf(" * %s\n", $failedTestName);

					$exception = $error->getException();
					printf("   - %s\n", $exception->getMessage());
				}

			}
		}

		public function _startTest(&$test) {

			$test->starttime = $this->microtime_float();

			if (phpversion() > '4') {
				$label = get_class($test)."::".$test->name();
				printf($label);
				for ($i = strlen($label); $i < 65; ++$i)
				print(" ");
			} else {
				$label = $test->name();
				printf($label);
				for ($i = strlen($label); $i < 65; ++$i)
				print(" ");
			}
			flush();
		}

		public function _endTest(&$test) {
			$duration = ($this->microtime_float() - $test->starttime);
			print("(".$this->readableDuration($duration).")  ");

			printf($this->passOrFail(!$test->failed())."\n");
			flush();
		}

		public function passOrFail($passed) {
			return ($passed
			? "[ \033[1;32mPASS\033[0m ]"
			: "[ \033[1;31mFAIL\033[0m ]");
		}

		public function readableDuration($time) {
			$label = null;
			$value = null;
			$pre = "";
			$post = " ";
			if ($time < 1) {
				$value = round($time * 1000, 0);
				$label = "ms";
				for ($i = strlen($value); $i < 7; ++$i)
				$pre .= " ";
			} else {
				$value = round($time, 3);
				$label = "s ";
				for ($i = strlen(round($value,0)); $i < 3; ++$i)
				$pre .= " ";
				for ($i = strlen($pre.$value); $i < 7; ++$i)
				$post .= " ";
			}

			return $pre.$value.$post.$label;
		}

		public function microtime_float() {
			list($usec, $sec) = explode(" ", microtime());
			return ((float)$usec + (float)$sec);
		} 
	}
