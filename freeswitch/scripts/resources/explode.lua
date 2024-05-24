
--add the explode function
	function explode(separator, str, limit)
		local pos, arr = 0, {}
		if separator ~= nil and str ~= nil then
			limit = limit or math.huge -- If limit is not provided, set it to infinity
			local count = 1
			for st, sp in function() return string.find(str, separator, pos, true) end do -- for each divider found
				if count < limit then
					table.insert(arr, string.sub(str, pos, st - 1)) -- attach chars left of current divider
					pos = sp + 1 -- jump past current divider
					count = count + 1
				else
					break
				end
			end
			table.insert(arr, string.sub(str, pos)) -- attach chars right of last divider
		end
		return arr
	end
