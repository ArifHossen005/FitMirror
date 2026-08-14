import type { ReactNode } from 'react';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import { chartPalette, colors } from '../tokens/colors';

export interface ChartSeries {
  key: string;
  label: string;
  color?: string;
}

interface BaseChartProps {
  /** Array of plain objects; each series' `key` must be a numeric field on every row. */
  data: Record<string, string | number>[];
  series: ChartSeries[];
  xKey: string;
  height?: number;
  showLegend?: boolean;
  showGrid?: boolean;
  valueFormatter?: (value: number) => string;
  className?: string;
}

const axisTick = { fill: colors.neutral[500], fontSize: 12 };

/**
 * `unknown` deliberately widens past Recharts' own `ValueType` (which can
 * include `undefined` and arrays depending on chart type) so this stays
 * assignable to every chart's `Tooltip formatter` prop regardless of the
 * exact generic Recharts infers for that instance.
 */
function tooltipFormatter(formatter?: (value: number) => string) {
  return (value: unknown): ReactNode => {
    if (formatter && typeof value === 'number') return formatter(value);
    if (typeof value === 'number' || typeof value === 'string') return value;
    return String(value ?? '');
  };
}

/** Trend-over-time chart (revenue, sessions, analytics rollups) — Recharts wrapped with FitMirror tokens. */
export function TrendLineChart({
  data,
  series,
  xKey,
  height = 280,
  showLegend = true,
  showGrid = true,
  valueFormatter,
  className,
}: BaseChartProps) {
  return (
    <div className={className} style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          {showGrid && <CartesianGrid strokeDasharray="3 3" stroke={colors.neutral[100]} />}
          <XAxis
            dataKey={xKey}
            tick={axisTick}
            tickLine={false}
            axisLine={{ stroke: colors.neutral[200] }}
          />
          <YAxis tick={axisTick} tickLine={false} axisLine={false} width={48} />
          <Tooltip
            formatter={tooltipFormatter(valueFormatter)}
            contentStyle={{
              borderRadius: 8,
              border: `1px solid ${colors.neutral[200]}`,
              fontSize: 13,
            }}
          />
          {showLegend && <Legend wrapperStyle={{ fontSize: 12 }} />}
          {series.map((item, index) => (
            <Line
              key={item.key}
              type="monotone"
              dataKey={item.key}
              name={item.label}
              stroke={item.color ?? chartPalette[index % chartPalette.length]}
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4 }}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

/** Filled trend chart — used where cumulative volume matters more than exact per-point comparison. */
export function TrendAreaChart({
  data,
  series,
  xKey,
  height = 280,
  showLegend = true,
  showGrid = true,
  valueFormatter,
  className,
}: BaseChartProps) {
  return (
    <div className={className} style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          {showGrid && <CartesianGrid strokeDasharray="3 3" stroke={colors.neutral[100]} />}
          <XAxis
            dataKey={xKey}
            tick={axisTick}
            tickLine={false}
            axisLine={{ stroke: colors.neutral[200] }}
          />
          <YAxis tick={axisTick} tickLine={false} axisLine={false} width={48} />
          <Tooltip
            formatter={tooltipFormatter(valueFormatter)}
            contentStyle={{
              borderRadius: 8,
              border: `1px solid ${colors.neutral[200]}`,
              fontSize: 13,
            }}
          />
          {showLegend && <Legend wrapperStyle={{ fontSize: 12 }} />}
          {series.map((item, index) => {
            const color = item.color ?? chartPalette[index % chartPalette.length];
            return (
              <Area
                key={item.key}
                type="monotone"
                dataKey={item.key}
                name={item.label}
                stroke={color}
                fill={color}
                fillOpacity={0.15}
                strokeWidth={2}
              />
            );
          })}
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

/** Comparison chart (category performance, campaign channel breakdown, multi-branch comparison). */
export function ComparisonBarChart({
  data,
  series,
  xKey,
  height = 280,
  showLegend = true,
  showGrid = true,
  valueFormatter,
  className,
}: BaseChartProps) {
  return (
    <div className={className} style={{ width: '100%', height }}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          {showGrid && <CartesianGrid strokeDasharray="3 3" stroke={colors.neutral[100]} />}
          <XAxis
            dataKey={xKey}
            tick={axisTick}
            tickLine={false}
            axisLine={{ stroke: colors.neutral[200] }}
          />
          <YAxis tick={axisTick} tickLine={false} axisLine={false} width={48} />
          <Tooltip
            formatter={tooltipFormatter(valueFormatter)}
            contentStyle={{
              borderRadius: 8,
              border: `1px solid ${colors.neutral[200]}`,
              fontSize: 13,
            }}
          />
          {showLegend && <Legend wrapperStyle={{ fontSize: 12 }} />}
          {series.map((item, index) => (
            <Bar
              key={item.key}
              dataKey={item.key}
              name={item.label}
              fill={item.color ?? chartPalette[index % chartPalette.length]}
              radius={[4, 4, 0, 0]}
            />
          ))}
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
