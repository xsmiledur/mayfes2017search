/*
 * aya_main.cpp
 *
 *  Created on: 2017/02/19
 *      Author: Aya
 */

#include <iostream>
#include <algorithm>
#include <time.h>
#include "aya_header.h"

using namespace std;

int main()
{
	int i, j, n, x, y, z;
	time_t current;
	struct tm *local;

	current = time(NULL);	// 現在時刻を取得
	local = localtime(&current);

	cin >> n;	// 入力（頂点数）

	int *v = 0;	// 頂点番号の配列
	int *s = 0;	// 到着したい時刻
	int *t = 0;	// 費やす時間
	v = new int[n + 1];
	s = new int[n + 1];
	t = new int[n + 1];
	cin >> v[0];	// 入力（始点）
	cin >> s[0];	// 開始時刻
//	s[0] = local -> tm_hour * 60 + local -> tm_min;
	cin >> t[0];	// 終了時刻
//	t[0] = 0;

	for(i = 1; i <= n; i++)
	{
		cin >> x >> y >> z;	// 入力（頂点番号, 到着したい時刻, 費やす時間）
		v[i] = x;
		s[i] = y;
		t[i] = z;
	}

/* 重み行列 */
	int **d = 0;
	d = new int*[n + 1];
	for(i = 0; i <= n; i++) d[i] = new int[n + 1];
	for(i = 0; i <= n; i++)
	{
		for(j = 0; j <= n; j++)
		{
			cin >> x;	// 入力（重み）
			d[i][j] = x;
		}
	}

	int *q = 0;	// 重み最小となる頂点の並びを保存する配列
	q = all_permutation_search(n, s, t, d);	// 全順列から重み最小の並びを探す

	if(q[0] == -1) cout << -1 << endl; // 出力（解なしの場合）
	else{
		cout << n << endl;	// 出力（頂点数）
		for(i = 0; i < n; i++) cout << v[q[i]] << endl;		// 出力（頂点番号）
	}

	delete[] v;
	delete[] s;
	delete[] t;
	delete[] q;
	for(i = 0; i <= n; i++) delete[] d[i];
	delete[] d;

	return 0;
}
