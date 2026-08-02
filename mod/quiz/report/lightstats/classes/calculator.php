<?php
// This file is part of Moodle - http://moodle.org/.

namespace quiz_lightstats;

defined('MOODLE_INTERNAL') || die();

/** Lightweight quiz statistics calculated from persisted final marks. */
class calculator {
    /** @var \moodle_database */
    private $db;

    public function __construct(\moodle_database $db) {
        $this->db = $db;
    }

    public function calculate(object $quiz): array {
        $attempts = array_values($this->db->get_records('quiz_attempts', [
            'quiz' => $quiz->id, 'preview' => 0, 'state' => 'finished'], 'userid, attempt',
            'id,userid,attempt,uniqueid,sumgrades'));
        $attempts = array_values(array_filter($attempts, static fn($a) => $a->sumgrades !== null));

        $byuser = [];
        foreach ($attempts as $attempt) {
            $byuser[$attempt->userid][] = $attempt;
        }
        $first = $last = $highest = [];
        foreach ($byuser as $userattempts) {
            $first[] = reset($userattempts);
            $last[] = end($userattempts);
            $best = reset($userattempts);
            foreach ($userattempts as $attempt) {
                if ($attempt->sumgrades > $best->sumgrades) {
                    $best = $attempt;
                }
            }
            $highest[] = $best;
        }

        $scale = $quiz->sumgrades > 0 ? 100 / $quiz->sumgrades : 0;
        $summary = [
            'firstcount' => count($first),
            'allcount' => count($attempts),
            'firstavg' => $this->mean($this->marks($first, $scale)),
            'allavg' => $this->mean($this->marks($attempts, $scale)),
            'lastavg' => $this->mean($this->marks($last, $scale)),
            'highestavg' => $this->mean($this->marks($highest, $scale)),
            'median' => $this->median($this->marks($highest, $scale)),
            'sd' => $this->sd($this->marks($highest, $scale)),
            'skewness' => $this->skewness($this->marks($highest, $scale)),
            'kurtosis' => $this->kurtosis($this->marks($highest, $scale)),
        ];

        [$questions, $responses, $matrix] = $this->question_data($quiz, $highest);
        $summary['alpha'] = $this->alpha($matrix, $this->marks($highest, 1.0));
        $summary['errorratio'] = $summary['alpha'] === null ? null : sqrt(max(0, 1 - $summary['alpha'])) * 100;
        $summary['se'] = $summary['errorratio'] === null || $summary['sd'] === null
            ? null : $summary['sd'] * $summary['errorratio'] / 100;

        return compact('summary', 'questions', 'responses');
    }

    private function question_data(object $quiz, array $attempts): array {
        if (!$attempts) {
            return [[], [], []];
        }
        $usageids = array_column($attempts, 'uniqueid');
        [$insql, $params] = $this->db->get_in_or_equal($usageids, SQL_PARAMS_NAMED, 'usage');
        $sql = "SELECT qa.id, qa.questionusageid, qa.slot, qa.questionid, qa.maxmark,
                       qa.questionsummary, qa.rightanswer, qa.responsesummary, q.qtype,
                       q.name, q.defaultmark, qas.fraction
                  FROM {question_attempts} qa
                  JOIN {question} q ON q.id = qa.questionid
             LEFT JOIN {question_attempt_steps} qas ON qas.id = (
                       SELECT MAX(s.id) FROM {question_attempt_steps} s
                        WHERE s.questionattemptid = qa.id AND s.fraction IS NOT NULL)
                 WHERE qa.questionusageid $insql
              ORDER BY qa.slot, qa.questionid, qa.questionusageid";
        $records = $this->db->get_records_sql($sql, $params);
        $usageindex = array_flip($usageids);
        $items = $responsecounts = $matrix = [];
        foreach ($records as $record) {
            $key = $record->slot . ':' . $record->questionid;
            if (!isset($items[$key])) {
                $items[$key] = [
                    'slot' => (int)$record->slot,
                    'questionid' => (int)$record->questionid,
                    'qtype' => $record->qtype,
                    'name' => $record->name ?: $record->questionsummary,
                    'maxmark' => (float)$record->maxmark,
                    'fractions' => [],
                ];
            }
            $fraction = $record->fraction === null ? null : (float)$record->fraction;
            $items[$key]['fractions'][] = $fraction;
            $row = $usageindex[$record->questionusageid];
            $matrix[$row][$record->slot] = $fraction ?? 0.0;
            $response = trim((string)$record->responsesummary);
            $response = $response === '' ? '[No response]' : $response;
            $responsecounts[$key][$response] = ($responsecounts[$key][$response] ?? 0) + 1;
            foreach ($this->model_responses((int)$record->questionid, $record->qtype) as $text => $credit) {
                $responsecounts[$key][$text] ??= 0;
                $items[$key]['credits'][$text] = $credit;
            }
        }

        $questions = $responses = [];
        $slotcounts = [];
        foreach ($items as $key => $item) {
            $slotcounts[$item['slot']] = ($slotcounts[$item['slot']] ?? 0) + 1;
            $number = (string)$item['slot'];
            if ($slotcounts[$item['slot']] > 1 || count(array_filter($items,
                    static fn($i) => $i['slot'] === $item['slot'])) > 1) {
                $number .= '.' . $slotcounts[$item['slot']];
            }
            $values = [];
            $totalmarks = [];
            foreach ($matrix as $row) {
                $values[] = $row[$item['slot']] ?? 0.0;
                $totalmarks[] = array_sum($row);
            }
            $facility = $this->mean($values);
            $discrimination = $this->correlation($values, $totalmarks);
            $questions[$key] = [
                'number' => $number,
                'type' => get_string('pluginname', 'qtype_' . $item['qtype']),
                'name' => $item['name'],
                'attempts' => count($values),
                'facility' => $facility === null ? null : $facility * 100,
                'sd' => ($sd = $this->sd($values)) === null ? null : $sd * 100,
                'randomguess' => $this->random_guess_score((int)$item['questionid'], $item['qtype']),
                'intendedweight' => $quiz->sumgrades > 0 ? $item['maxmark'] / $quiz->sumgrades * 100 : null,
                'effectiveweight' => null,
                'discrimination' => $discrimination === null ? null : $discrimination * 100,
                'efficiency' => $discrimination === null ? null : $discrimination * 100,
            ];
            if ($item['qtype'] === 'essay') {
                continue;
            }
            $total = array_sum($responsecounts[$key]);
            foreach ($responsecounts[$key] as $response => $count) {
                $responses[$key][] = [
                    'response' => $response,
                    'partialcredit' => $item['credits'][$response] ?? 0.0,
                    'count' => $count,
                    'frequency' => $total ? $count / $total * 100 : 0,
                ];
            }
            usort($responses[$key], static function($a, $b) {
                return ($a['response'] === '[No response]') <=> ($b['response'] === '[No response]');
            });
        }
        return [$questions, $responses, $matrix];
    }

    private function model_responses(int $questionid, string $qtype): array {
        if (!in_array($qtype, ['multichoice', 'truefalse'], true)) {
            return [];
        }
        $answers = $this->db->get_records('question_answers', ['question' => $questionid], 'id', 'answer,fraction');
        $responses = [];
        foreach ($answers as $answer) {
            $responses[trim(strip_tags($answer->answer))] = (float)$answer->fraction * 100;
        }
        return $responses;
    }

    private function random_guess_score(int $questionid, string $qtype): float {
        if ($qtype === 'truefalse') {
            return 50.0;
        }
        if ($qtype !== 'multichoice') {
            return 0.0;
        }
        $count = $this->db->count_records('question_answers', ['question' => $questionid]);
        return $count ? 100 / $count : 0.0;
    }

    private function marks(array $attempts, float $scale): array {
        return array_map(static fn($a) => (float)$a->sumgrades * $scale, $attempts);
    }

    private function mean(array $values): ?float {
        return $values ? array_sum($values) / count($values) : null;
    }

    private function median(array $values): ?float {
        if (!$values) return null;
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);
        return count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private function sd(array $values): ?float {
        if (count($values) < 2) return null;
        $mean = $this->mean($values);
        return sqrt(array_sum(array_map(static fn($v) => ($v - $mean) ** 2, $values)) / (count($values) - 1));
    }

    private function skewness(array $values): ?float {
        $n = count($values); $sd = $this->sd($values);
        if ($n < 3 || !$sd) return null;
        $mean = $this->mean($values);
        return $n / (($n - 1) * ($n - 2)) * array_sum(array_map(static fn($v) => (($v - $mean) / $sd) ** 3, $values));
    }

    private function kurtosis(array $values): ?float {
        $n = count($values); $sd = $this->sd($values);
        if ($n < 4 || !$sd) return null;
        $mean = $this->mean($values);
        $sum = array_sum(array_map(static fn($v) => (($v - $mean) / $sd) ** 4, $values));
        return $n * ($n + 1) / (($n - 1) * ($n - 2) * ($n - 3)) * $sum
            - 3 * (($n - 1) ** 2) / (($n - 2) * ($n - 3));
    }

    private function correlation(array $x, array $y): ?float {
        $n = min(count($x), count($y));
        if ($n < 2) return null;
        $x = array_slice($x, 0, $n); $y = array_slice($y, 0, $n);
        $mx = $this->mean($x); $my = $this->mean($y);
        $cross = $xx = $yy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $mx; $dy = $y[$i] - $my;
            $cross += $dx * $dy; $xx += $dx ** 2; $yy += $dy ** 2;
        }
        return $xx && $yy ? $cross / sqrt($xx * $yy) : null;
    }

    private function alpha(array $matrix, array $totals): ?float {
        if (count($matrix) < 2) return null;
        $slots = [];
        foreach ($matrix as $row) $slots += array_fill_keys(array_keys($row), 0);
        $k = count($slots);
        if ($k < 2) return null;
        $itemvariances = 0.0;
        foreach (array_keys($slots) as $slot) {
            $column = [];
            foreach ($matrix as $row) $column[] = $row[$slot] ?? 0.0;
            $itemvariances += $this->sd($column) ** 2;
        }
        $totalvariance = $this->sd($totals) ** 2;
        return $totalvariance ? $k / ($k - 1) * (1 - $itemvariances / $totalvariance) : null;
    }
}
