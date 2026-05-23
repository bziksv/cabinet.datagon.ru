<?php

namespace App\Support;

final class TfidfMetrics
{
  /**
   * TF по агрегированному корпусу: count / totalTerms.
   */
  public static function termFrequency(float $termCount, float $totalTerms): float
  {
    $totalTerms = max(1.0, $totalTerms);

    return round($termCount / $totalTerms, 7);
  }

  /**
   * IDF: log10(N / df), N — число документов (конкурентов), df — число документов с термином.
   */
  public static function inverseDocumentFrequency(int $documentCount, int $documentFrequency): float
  {
    $documentCount = max(1, $documentCount);
    $documentFrequency = max(1, $documentFrequency);

    return round(log10($documentCount / $documentFrequency), 7);
  }

  public static function score(float $tf, float $idf): float
  {
    return round($tf * $idf, 7);
  }
}
