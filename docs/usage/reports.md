---
title: Reports
---

# Reports

`SpecReport` rolls results into four-tier breakdowns for the whole site, each category, and each page. It deliberately has **no single "overall %"**: a missing Required item and a missing Optional item are not comparable.

```php
$report = specification()->report( $results );

$report->site();                      // TierBreakdown
$report->category( 'security' );      // TierBreakdown|null
$report->categories();                // array<string, TierBreakdown>
$report->page( 'https://example.com/about' );
$report->pages();
$report->results();
$report->toArray();                   // also JsonSerializable
```

## TierBreakdown

| Property | Meaning |
|----------|---------|
| `required` | `TierScore` of Required checks met |
| `recommended` | `TierScore` of Recommended checks met |
| `optional` | `TierScore` of Optional checks met |
| `avoidPresent` | Number of Avoid checks whose avoided practice was found |
| `avoidChecked` | Number of Avoid checks evaluated |
| `skipped` | Results that were not applicable |
| `errored` | Results that errored |

A `TierScore` has `met`, `total`, and `percent()`. `percent()` is rounded to one decimal place, or `null` when nothing in the tier was evaluated.

## Grouping

- Page, Section, and Block results are grouped under their page's subject (its URL).
- Site and Server results feed only the site and category breakdowns.
