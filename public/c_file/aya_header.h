/*
 * aya_header.h
 *
 *  Created on: 2017/02/19
 *      Author: Aya
 */

#include <iostream>
#include <algorithm>

using namespace std;

#ifndef AYA_HEADER_H_
#define AYA_HEADER_H_

bool valid(int size, int *start, int *timelength, int **dm, int *index);
int *all_permutation_search(int size, int *start, int *timelength, int **dm);

#endif
