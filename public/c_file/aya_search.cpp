/*
 * aya_search.cpp
 *
 *  Created on: 2017/02/19
 *      Author: Aya
 */

#include <iostream>
#include <algorithm>
#include "aya_header.h"

using namespace std;

#define INFINITY 99999999

bool valid(int size, int *start, int *timelength, int **dm, int *index)
{
	int i, nowplace, nextplace, nowtime, nexttime, dl;
	nowtime = start[0];
	nowplace = 0;
	for(i = 0; i < size; i++)
	{
		nextplace = index[i];
		nexttime = nowtime + dm[nowplace][nextplace];
		dl = start[nextplace];
		if(dl != -1 && dl < nexttime) return false;
		else{
			nowplace = nextplace;
			nowtime = max(nexttime, dl) + timelength[nextplace];
		}
	}
	if(nowtime > timelength[0]) return false;
	else return true;
}

/* 全順列から重み最小の並びを探す */
int *all_permutation_search(int size, int *start, int *timelength, int **dm)
{
	int d, i, j, min;

	min = INFINITY;

	int *index = 0;
	index = new int[size];
	for(i = 0; i < size; i++) index[i] = i + 1;	// 配列 1,...,n を作成

	int *index_min = 0;	// 重み最小の順列を保存する配列
	index_min = new int[size];
	index_min[0] = -1;

	do
	{
		if(valid(size, start, timelength, dm, index))
		{
			d = dm[0][index[0]];
			for(i = 1; i < size; i++) d += dm[index[i - 1]][index[i]];
			// 2頂点間の重みを足す
			if(d < min)
			{
				min = d; // 最小値を更新
				for(j = 0; j < size; j++) index_min[j] = index[j];
				// 重み最小の順列を保存
			}
		}
	}
	while(next_permutation(index, index + size));
	// 次の順列が存在する間繰り返す

	delete[] index;

	return index_min;
}
